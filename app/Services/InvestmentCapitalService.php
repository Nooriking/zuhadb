<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorTransaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Internal capital pot creation. Investor Details is the user-facing entry point;
 * Investment rows remain the ledger source of truth for allocations and history.
 */
class InvestmentCapitalService
{
    public function __construct(
        private readonly CurrencyService $currencies,
    ) {}

    /**
     * @param  array{
     *     user_id:int,
     *     source_type:string,
     *     capital_amount?:float|string|null,
     *     capital_amount_afn?:float|string|null,
     *     currency_id?:int|null,
     *     exchange_rate?:float|string|null,
     *     investment_date:string,
     *     effective_at?:string|null,
     *     notes?:string|null,
     *     parent_investment_id?:int|null
     * }  $data
     */
    public function deposit(array $data): Investment
    {
        return DB::transaction(function () use ($data) {
            $user = User::query()
                ->notTrashed()
                ->where('role', User::ROLE_INVESTOR)
                ->where('status', 'active')
                ->findOrFail($data['user_id']);

            $investor = Investor::query()->whereKey($user->id)->firstOrFail();
            $investor->getOrCreateWallet();

            $sourceType = $data['source_type'] ?? 'cash';
            $capitalEntered = Money::round((string) ($data['capital_amount'] ?? $data['capital_amount_afn'] ?? '0'), 8);
            $effectiveAt = $data['effective_at'] ?? $data['investment_date'] ?? now();
            $explicitRate = isset($data['exchange_rate']) && (float) $data['exchange_rate'] > 0
                ? Money::round((string) $data['exchange_rate'], 8)
                : null;

            $capitalAfn = $capitalEntered;
            $currencySnap = [
                'currency_id' => null,
                'currency_code' => null,
                'currency_symbol' => null,
                'exchange_rate' => null,
                'capital_amount_currency' => (float) $capitalEntered > 0 ? $capitalEntered : null,
            ];

            if ((float) $capitalEntered > 0) {
                $currency = $this->currencies->requireActive((int) ($data['currency_id'] ?? 0));
                $ledger = Currency::ledger() ?? $currency;

                if ($explicitRate !== null) {
                    $rate = Money::round($explicitRate, 8);
                    $capitalAfn = Money::round(Money::mul($capitalEntered, $rate, 12), 2);
                } else {
                    $resolved = $this->currencies->resolveAt($currency, $ledger, $effectiveAt);
                    $rate = (string) $resolved['rate'];
                    $capitalAfn = Money::round(
                        $this->currencies->convertAt($capitalEntered, $currency, $ledger, $effectiveAt),
                        2
                    );
                }

                $currencySnap = [
                    'currency_id' => $currency->id,
                    'currency_code' => $currency->code,
                    'currency_symbol' => $currency->symbol,
                    'exchange_rate' => $rate,
                    'capital_amount_currency' => $capitalEntered,
                ];
            } elseif ($sourceType === 'goods') {
                throw ValidationException::withMessages([
                    'capital_amount' => 'Goods contributions require a valued amount and currency.',
                ]);
            }

            // Goods capital is not withdrawable cash — available_amount stays 0.
            $availableAmount = $sourceType === 'cash' ? $capitalAfn : '0';

            $payload = [
                'investor_id' => $investor->id,
                'user_id' => $investor->id,
                'parent_investment_id' => $data['parent_investment_id'] ?? null,
                'investment_number' => Investment::generateInvestmentNumber(),
                'source_type' => $sourceType,
                'capital_amount_afn' => $capitalAfn,
                'available_amount_afn' => $availableAmount,
                'available_amount_currency' => $sourceType === 'cash' ? $capitalEntered : 0,
                'allocated_amount_afn' => 0,
                'profit_amount_afn' => 0,
                'withdrawn_amount_afn' => 0,
                'investment_date' => $data['investment_date'],
                'notes' => $data['notes'] ?? null,
                'status' => 'active',
                'created_by' => Auth::id(),
            ];

            $payload = array_merge($payload, $currencySnap);

            $investment = Investment::create($payload);

            if ((float) $capitalAfn > 0) {
                InvestorTransaction::record(
                    $investment->investor_id,
                    $sourceType === 'cash' ? 'capital_deposit' : 'goods_deposit',
                    $capitalAfn,
                    'capital',
                    [
                        'investment_id' => $investment->id,
                        'currency_id' => $currencySnap['currency_id'],
                        'currency_code' => $currencySnap['currency_code'],
                        'currency_symbol' => $currencySnap['currency_symbol'],
                        'exchange_rate' => $currencySnap['exchange_rate'],
                        'amount_currency' => $capitalEntered,
                        'description' => ($sourceType === 'cash' ? 'Capital' : 'Goods').' deposit '.$investment->investment_number,
                        'transaction_date' => $investment->investment_date?->toDateString(),
                    ]
                );

                $wallet = $investment->investor->getOrCreateWallet();

                if ($sourceType === 'cash') {
                    $wallet->update([
                        'capital_balance_afn' => Money::round(
                            Money::add((string) $wallet->capital_balance_afn, (string) $capitalAfn, 8),
                            2
                        ),
                        'total_invested_afn' => Money::round(
                            Money::add((string) $wallet->total_invested_afn, (string) $capitalAfn, 8),
                            2
                        ),
                        'last_transaction_at' => now(),
                    ]);
                } else {
                    // Goods must never increase cash wallet capital.
                    $wallet->update([
                        'total_invested_afn' => Money::round(
                            Money::add((string) $wallet->total_invested_afn, (string) $capitalAfn, 8),
                            2
                        ),
                        'last_transaction_at' => now(),
                    ]);
                }
            }

            return $investment;
        });
    }
}
