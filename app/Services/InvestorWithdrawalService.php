<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Investor;
use App\Models\InvestorTransaction;
use App\Models\InvestorWalletBalance;
use App\Models\InvestorWithdrawal;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Investor profit/capital withdrawals with multi-currency FX snapshots.
 *
 * Source of truth:
 * - Profit available = SUM(investor_wallet_balances.profit_balance_afn)
 *   (sale profits are credited to the AFN wallet row).
 * - Capital available for a currency = that currency's capital_balance_currency,
 *   with AFN ledger check against capital_balance_afn on the same row / totals.
 * - Pending/rejected withdrawals do NOT reduce wallet balances.
 * - Wallet deduction happens only when status becomes paid.
 */
class InvestorWithdrawalService
{
    public function __construct(
        private readonly CurrencyService $currencies,
    ) {}

    /**
     * @param  array{
     *   investor_id:int,
     *   investment_id?:int|null,
     *   type:string,
     *   currency_id:int,
     *   amount?:float|string|null,
     *   amount_afn?:float|string|null,
     *   withdrawal_date:string,
     *   status?:string|null,
     *   notes?:string|null,
     * }  $data
     */
    public function create(array $data, ?int $userId = null): InvestorWithdrawal
    {
        return DB::transaction(function () use ($data, $userId) {
            /** @var Investor $investor */
            $investor = Investor::query()
                ->whereKey((int) $data['investor_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($investor->status !== 'active') {
                throw ValidationException::withMessages([
                    'investor_id' => 'Investor is not active.',
                ]);
            }

            $currency = $this->currencies->requireActive((int) $data['currency_id']);
            $entered = $data['amount'] ?? $data['amount_currency'] ?? $data['amount_afn'] ?? null;

            if ($entered === null || $entered === '' || (float) $entered <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Withdrawal amount must be greater than zero.',
                ]);
            }

            $amountCurrency = Money::round((string) $entered, 2);
            $at = $data['withdrawal_date'] ?? now();
            $resolved = $this->currencies->resolveAt(
                $currency,
                Currency::ledger() ?? $currency,
                $at,
                allowCatalogFallback: true,
            );

            if ((float) $resolved['rate'] <= 0) {
                throw ValidationException::withMessages([
                    'currency_id' => 'A valid exchange rate is required for '.$currency->code.'.',
                ]);
            }

            $rate = Money::round((string) $resolved['rate'], 8);
            $amountAfn = Money::round($this->currencies->toLedger($amountCurrency, $currency, $at), 2);
            $snap = $this->currencies->snapshot($currency);
            $type = $data['type'] === 'capital' ? 'capital' : 'profit';

            $this->assertSufficientBalance($investor, $type, $currency->code, $amountCurrency, $amountAfn);

            $balanceBefore = $type === 'profit'
                ? $this->availableProfitAfn($investor)
                : $this->availableCapitalAfn($investor);

            $status = $data['status'] ?? 'pending';
            if (! in_array($status, ['pending', 'approved', 'paid'], true)) {
                $status = 'pending';
            }

            $withdrawal = InvestorWithdrawal::create([
                'investor_id' => $investor->id,
                'investment_id' => $data['investment_id'] ?? null,
                'currency_id' => $snap['id'],
                'currency_code' => $snap['code'],
                'currency_symbol' => $snap['symbol'],
                'exchange_rate' => $rate,
                'withdrawal_number' => InvestorWithdrawal::generateWithdrawalNumber(),
                'type' => $type,
                'amount_currency' => $amountCurrency,
                'amount_afn' => $amountAfn,
                'balance_before_afn' => Money::round((string) $balanceBefore, 2),
                'balance_after_afn' => Money::round(Money::sub((string) $balanceBefore, $amountAfn, 8), 2),
                'withdrawal_date' => $data['withdrawal_date'],
                'status' => $status === 'paid' ? 'approved' : $status,
                'notes' => $data['notes'] ?? null,
                'approved_by' => in_array($status, ['approved', 'paid'], true) ? ($userId ?? Auth::id()) : null,
                'approved_at' => in_array($status, ['approved', 'paid'], true) ? now() : null,
                'created_by' => $userId ?? Auth::id(),
            ]);

            // Deduct only when explicitly created as paid (atomic create+pay).
            if ($status === 'paid') {
                $this->applyPaidEffects($withdrawal->fresh(), $userId);
            }

            return $withdrawal->fresh();
        });
    }

    public function markAsPaid(InvestorWithdrawal $withdrawal, ?int $userId = null): InvestorWithdrawal
    {
        return DB::transaction(function () use ($withdrawal, $userId) {
            $locked = InvestorWithdrawal::query()
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'paid') {
                throw ValidationException::withMessages([
                    'status' => 'Withdrawal is already paid.',
                ]);
            }

            if ($locked->status === 'rejected') {
                throw ValidationException::withMessages([
                    'status' => 'Rejected withdrawals cannot be paid.',
                ]);
            }

            return $this->applyPaidEffects($locked, $userId);
        });
    }

    public function availableProfitAfn(Investor $investor): float
    {
        return round((float) InvestorWalletBalance::query()
            ->where('investor_id', $investor->id)
            ->sum('profit_balance_afn'), 2);
    }

    public function availableCapitalAfn(Investor $investor): float
    {
        return round((float) InvestorWalletBalance::query()
            ->where('investor_id', $investor->id)
            ->sum('capital_balance_afn'), 2);
    }

    /**
     * @return array{profit_afn: float, capital_afn: float, by_currency: list<array<string,mixed>>}
     */
    public function balanceSummary(Investor $investor): array
    {
        $rows = InvestorWalletBalance::query()
            ->where('investor_id', $investor->id)
            ->orderBy('currency_code')
            ->get();

        return [
            'profit_afn' => $this->availableProfitAfn($investor),
            'capital_afn' => $this->availableCapitalAfn($investor),
            'by_currency' => $rows->map(fn (InvestorWalletBalance $row) => [
                'currency_code' => $row->currency_code,
                'capital_balance_currency' => (float) $row->capital_balance_currency,
                'capital_balance_afn' => (float) $row->capital_balance_afn,
                'profit_balance_currency' => (float) $row->profit_balance_currency,
                'profit_balance_afn' => (float) $row->profit_balance_afn,
            ])->values()->all(),
        ];
    }

    private function applyPaidEffects(InvestorWithdrawal $withdrawal, ?int $userId = null): InvestorWithdrawal
    {
        $investor = Investor::query()
            ->whereKey($withdrawal->investor_id)
            ->lockForUpdate()
            ->firstOrFail();

        $amountAfn = Money::round((string) $withdrawal->amount_afn, 2);
        $amountCurrency = Money::round((string) ($withdrawal->amount_currency ?? $amountAfn), 2);
        $currencyCode = strtoupper((string) ($withdrawal->currency_code ?: 'AFN'));
        $rate = (float) ($withdrawal->exchange_rate ?: 1);

        // Re-validate against live balances under lock.
        $this->assertSufficientBalance($investor, $withdrawal->type, $currencyCode, $amountCurrency, $amountAfn);

        if ($withdrawal->isProfitWithdrawal()) {
            // Profit is always held on the AFN wallet row (sale credits).
            $afnRow = InvestorWalletBalance::query()
                ->where('investor_id', $investor->id)
                ->where('currency_code', 'AFN')
                ->lockForUpdate()
                ->first();

            if (! $afnRow) {
                $afnRow = InvestorWalletBalance::forInvestor($investor->id, 'AFN');
                $afnRow = InvestorWalletBalance::query()->whereKey($afnRow->id)->lockForUpdate()->firstOrFail();
            }

            $afnRow->adjustProfit(-1 * (float) $amountAfn, -1 * (float) $amountAfn, $rate);
            $txType = 'profit_withdrawn';
            $balanceType = 'profit';
        } else {
            // Capital: deduct from the withdrawal currency row (capital is multi-currency).
            $row = InvestorWalletBalance::query()
                ->where('investor_id', $investor->id)
                ->where('currency_code', $currencyCode)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                $row = InvestorWalletBalance::forInvestor($investor->id, $currencyCode);
                $row = InvestorWalletBalance::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            }

            $row->adjustCapital(-1 * (float) $amountCurrency, -1 * (float) $amountAfn, $rate);
            $txType = 'capital_withdrawn';
            $balanceType = 'capital';
        }

        // Avoid duplicate investor_transactions if markAsPaid is retried after partial failure.
        $existingTx = InvestorTransaction::query()
            ->where('reference_type', InvestorWithdrawal::class)
            ->where('reference_id', $withdrawal->id)
            ->where('type', $txType)
            ->exists();

        if (! $existingTx) {
            InvestorTransaction::record(
                investorId: $investor->id,
                type: $txType,
                amountAfn: (float) $amountAfn,
                balanceType: $balanceType,
                options: [
                    'investment_id' => $withdrawal->investment_id,
                    'currency_id' => $withdrawal->currency_id,
                    'currency_code' => $withdrawal->currency_code,
                    'currency_symbol' => $withdrawal->currency_symbol,
                    'exchange_rate' => $withdrawal->exchange_rate,
                    'amount_currency' => (float) $amountCurrency,
                    'reference_type' => InvestorWithdrawal::class,
                    'reference_id' => $withdrawal->id,
                    'description' => "Withdrawal {$withdrawal->withdrawal_number}",
                    'transaction_date' => optional($withdrawal->withdrawal_date)?->toDateString() ?? now()->toDateString(),
                ]
            );
        }

        $remainingAfn = $withdrawal->isProfitWithdrawal()
            ? $this->availableProfitAfn($investor)
            : $this->availableCapitalAfn($investor);

        $withdrawal->update([
            'status' => 'paid',
            'paid_by' => $userId ?? Auth::id(),
            'paid_at' => now(),
            'approved_by' => $withdrawal->approved_by ?? ($userId ?? Auth::id()),
            'approved_at' => $withdrawal->approved_at ?? now(),
            'balance_after_afn' => Money::round((string) $remainingAfn, 2),
        ]);

        return $withdrawal->fresh();
    }

    private function assertSufficientBalance(
        Investor $investor,
        string $type,
        string $currencyCode,
        string $amountCurrency,
        string $amountAfn,
    ): void {
        $currencyCode = strtoupper($currencyCode);

        if ($type === 'profit') {
            $availableAfn = $this->availableProfitAfn($investor);

            if ((float) $amountAfn > $availableAfn + 0.01) {
                throw ValidationException::withMessages([
                    'amount' => sprintf(
                        'Insufficient available profit. Requested %s %s (≈ %s AFN) but only %s AFN is available.',
                        $amountCurrency,
                        $currencyCode,
                        $amountAfn,
                        number_format($availableAfn, 2, '.', '')
                    ),
                ]);
            }

            return;
        }

        $row = InvestorWalletBalance::forInvestor($investor->id, $currencyCode);
        $availableCurrency = (float) $row->capital_balance_currency;
        $availableAfn = (float) $row->capital_balance_afn;

        if ((float) $amountCurrency > $availableCurrency + 0.01 || (float) $amountAfn > $availableAfn + 0.01) {
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    'Insufficient cash capital in %s. Available: %s %s (≈ %s AFN). Goods capital cannot be withdrawn as cash.',
                    $currencyCode,
                    number_format($availableCurrency, 2, '.', ''),
                    $currencyCode,
                    number_format($availableAfn, 2, '.', '')
                ),
            ]);
        }
    }
}
