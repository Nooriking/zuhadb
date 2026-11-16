<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\InvestorProfitEntry;
use App\Models\InvestorWallet;
use App\Models\InvestorWithdrawal;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Support\Money;
use App\Support\OriginalCurrencyMoney;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Authoritative multi-currency investor dashboard valuation.
 *
 * Rules:
 * - Never cross-add AFN + USD + AED originals.
 * - Preserve historical purchase FX snapshots; do not revalue with today's board.
 * - Weighted-average FX is for inventory / FX reporting only (FIFO remains sales costing).
 * - When original currency cannot be proven, mark the metric unavailable / legacy.
 */
class InvestorMultiCurrencyDashboardService
{
    public function __construct(
        private readonly CurrencyService $currencies,
    ) {}

    /**
     * @return array{
     *     currency_sections: array<string, array<string, mixed>>,
     *     exchange_valuation: array<string, mixed>,
     *     legacy: array<string, mixed>,
     *     limitations: list<string>,
     *     formulas: array<string, string>
     * }
     */
    public function summarize(?int $investorId = null): array
    {
        $sections = [];
        foreach (OriginalCurrencyMoney::CODES as $code) {
            $sections[$code] = $this->emptySection($code);
        }

        $legacy = [
            'profit_afn_without_currency' => '0.00',
            'withdrawals_afn_without_currency' => '0.00',
            'purchases_without_currency' => 0,
            'note' => 'Legacy rows without a reliable original currency are not attributed to AFN/USD/AED originals.',
        ];

        $limitations = [];

        $this->accumulateInvestments($sections, $investorId);
        $this->accumulateInventory($sections, $investorId, $legacy, $limitations);
        $this->accumulateProfits($sections, $legacy, $investorId);
        $this->accumulateWithdrawals($sections, $legacy, $investorId);
        $this->accumulateBalances($sections, $investorId, $limitations);

        $exchange = $this->buildExchangeValuation($investorId);

        foreach ($sections as $code => $section) {
            $sections[$code] = $this->finalizeSection($section);
        }

        return [
            'currency_sections' => $sections,
            'exchange_valuation' => $exchange,
            'legacy' => $legacy,
            'limitations' => array_values(array_unique($limitations)),
            'formulas' => [
                'weighted_average_fx' => 'Sum(remaining historical AFN cost) / Sum(remaining original-currency cost) for one original currency only',
                'remaining_inventory_original' => 'Sum(remaining_quantity × original unit purchase cost) grouped by purchase_currency_code',
                'remaining_inventory_afn' => 'Sum(remaining_quantity × historical unit_cost_afn) — ledger cost, not a current-market revaluation',
                'fifo' => 'Sales costing continues to consume batches FIFO; weighted average is reporting-only',
                'current_balance_afn' => 'investor_wallets.capital_balance_afn + profit_balance_afn',
                'current_balance_foreign' => 'Sum(available_amount_currency for cash investments in that currency) + pending profit_amount_currency in that currency — never derived by converting the AFN wallet',
            ],
        ];
    }

    /**
     * Weighted average FX for remaining inventory of a single original currency.
     *
     * @param  iterable<int, array{original_amount: string|float|null, afn_amount: string|float|null, quantity?: int|null}>  $rows
     * @return array{
     *     rate: string|null,
     *     original_amount: string,
     *     historical_afn: string,
     *     units: int,
     *     rows: int,
     *     kind: string,
     *     available: bool
     * }
     */
    public function weightedAverageFromRemaining(iterable $rows): array
    {
        $original = '0';
        $afn = '0';
        $units = 0;
        $count = 0;

        foreach ($rows as $row) {
            $orig = (string) ($row['original_amount'] ?? '0');
            $afnPiece = (string) ($row['afn_amount'] ?? '0');
            if ((float) $orig <= 0 || (float) $afnPiece <= 0) {
                continue;
            }
            $original = Money::add($original, $orig, 8);
            $afn = Money::add($afn, $afnPiece, 8);
            $units += max(0, (int) ($row['quantity'] ?? 0));
            $count++;
        }

        $rate = $this->currencies->weightedAverageFxFromEquivalents([
            ['original_amount' => $original, 'afn_amount' => $afn],
        ]);

        return [
            'rate' => $rate,
            'original_amount' => Money::round($original, 2),
            'historical_afn' => Money::round($afn, 2),
            'units' => $units,
            'rows' => $count,
            'kind' => 'historical_weighted_average',
            'available' => $rate !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySection(string $code): array
    {
        return [
            'code' => $code,
            'label' => OriginalCurrencyMoney::label($code),
            'cash_capital' => '0.00',
            'goods_purchase_value' => '0.00',
            'inventory_value' => '0.00',
            'sold_goods_value' => '0.00',
            'investor_profit' => '0.00',
            'paid_profit' => '0.00',
            'pending_profit' => '0.00',
            'withdrawals' => '0.00',
            'current_balance' => null,
            'current_balance_available' => false,
            'historical_afn' => [
                'cash_capital' => '0.00',
                'goods_purchase_value' => '0.00',
                'inventory_value' => '0.00',
                'sold_goods_value' => '0.00',
                'investor_profit' => '0.00',
                'paid_profit' => '0.00',
                'pending_profit' => '0.00',
                'withdrawals' => '0.00',
            ],
            'metrics_available' => [
                'cash_capital' => true,
                'goods_purchase_value' => true,
                'inventory_value' => true,
                'sold_goods_value' => true,
                'investor_profit' => true,
                'paid_profit' => true,
                'pending_profit' => true,
                'withdrawals' => true,
                'current_balance' => false,
            ],
            'batch_count' => 0,
            'unit_count' => 0,
            'sold_unit_count' => 0,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $sections
     */
    private function accumulateInvestments(array &$sections, ?int $investorId): void
    {
        $query = Investment::notTrashed()
            ->when($investorId, fn ($q) => $q->where('investor_id', $investorId));

        foreach ($query->get([
            'source_type',
            'currency_code',
            'capital_amount_currency',
            'capital_amount_afn',
        ]) as $row) {
            $code = $this->normalizeKnownCode($row->currency_code);
            if ($code === null) {
                continue;
            }

            $original = Money::round((string) ($row->capital_amount_currency ?? $row->capital_amount_afn ?? '0'), 2);
            $afn = Money::round((string) ($row->capital_amount_afn ?? '0'), 2);

            if ($row->source_type === 'cash') {
                $sections[$code]['cash_capital'] = Money::add($sections[$code]['cash_capital'], $original, 2);
                $sections[$code]['historical_afn']['cash_capital'] = Money::add(
                    $sections[$code]['historical_afn']['cash_capital'],
                    $afn,
                    2
                );
            } elseif ($row->source_type === 'goods') {
                $sections[$code]['goods_purchase_value'] = Money::add($sections[$code]['goods_purchase_value'], $original, 2);
                $sections[$code]['historical_afn']['goods_purchase_value'] = Money::add(
                    $sections[$code]['historical_afn']['goods_purchase_value'],
                    $afn,
                    2
                );
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $sections
     * @param  array<string, mixed>  $legacy
     * @param  list<string>  $limitations
     */
    private function accumulateInventory(array &$sections, ?int $investorId, array &$legacy, array &$limitations): void
    {
        $batches = $this->investorBatches($investorId);

        if ($batches->isEmpty()) {
            // Fall back to purchase rows when batches are absent (legacy paths).
            $this->accumulateInventoryFromPurchases($sections, $investorId, $legacy, $limitations);

            return;
        }

        $goodsFromInvestments = [];
        foreach (OriginalCurrencyMoney::CODES as $code) {
            $goodsFromInvestments[$code] = (float) $sections[$code]['goods_purchase_value'] > 0.0;
        }

        foreach ($batches as $batch) {
            $purchase = $batch->purchaseItem;
            $code = $this->normalizeKnownCode($purchase?->purchase_currency_code);
            if ($code === null) {
                $legacy['purchases_without_currency'] = (int) $legacy['purchases_without_currency'] + 1;
                $limitations[] = 'Some inventory batches lack purchase_currency_code and are excluded from original-currency sections.';

                continue;
            }

            $remainingQty = max(0, (int) $batch->remaining_quantity);
            $receivedQty = max(0, (int) $batch->quantity);
            $soldQty = max(0, $receivedQty - $remainingQty);

            $unitOriginal = (string) ($batch->unit_cost_currency
                ?? $purchase?->purchase_unit_amount
                ?? '0');
            $unitAfn = (string) ($batch->unit_cost_afn ?? '0');

            if ((float) $unitOriginal <= 0 && $purchase) {
                $unitOriginal = (string) ($purchase->purchase_unit_amount ?? '0');
            }
            if ((float) $unitAfn <= 0 && $purchase && (int) ($purchase->quantity ?? 0) > 0) {
                $unitAfn = Money::div((string) ($purchase->purchase_price_afn ?? $purchase->line_total ?? '0'), (string) max(1, (int) $purchase->quantity), 8);
            }

            $remainingOriginal = Money::round(Money::mul($unitOriginal, (string) $remainingQty, 8), 2);
            $remainingAfn = Money::round(Money::mul($unitAfn, (string) $remainingQty, 8), 2);
            $soldOriginal = Money::round(Money::mul($unitOriginal, (string) $soldQty, 8), 2);
            $soldAfn = Money::round(Money::mul($unitAfn, (string) $soldQty, 8), 2);

            // Prefer goods contribution from investments when present; otherwise sum received batch costs.
            if (! $goodsFromInvestments[$code]) {
                $receivedOriginal = Money::round(Money::mul($unitOriginal, (string) $receivedQty, 8), 2);
                $receivedAfn = Money::round(Money::mul($unitAfn, (string) $receivedQty, 8), 2);
                $sections[$code]['goods_purchase_value'] = Money::add($sections[$code]['goods_purchase_value'], $receivedOriginal, 2);
                $sections[$code]['historical_afn']['goods_purchase_value'] = Money::add(
                    $sections[$code]['historical_afn']['goods_purchase_value'],
                    $receivedAfn,
                    2
                );
            }

            $sections[$code]['inventory_value'] = Money::add($sections[$code]['inventory_value'], $remainingOriginal, 2);
            $sections[$code]['historical_afn']['inventory_value'] = Money::add(
                $sections[$code]['historical_afn']['inventory_value'],
                $remainingAfn,
                2
            );
            $sections[$code]['sold_goods_value'] = Money::add($sections[$code]['sold_goods_value'], $soldOriginal, 2);
            $sections[$code]['historical_afn']['sold_goods_value'] = Money::add(
                $sections[$code]['historical_afn']['sold_goods_value'],
                $soldAfn,
                2
            );
            $sections[$code]['batch_count']++;
            $sections[$code]['unit_count'] += $remainingQty;
            $sections[$code]['sold_unit_count'] += $soldQty;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $sections
     * @param  array<string, mixed>  $legacy
     * @param  list<string>  $limitations
     */
    private function accumulateInventoryFromPurchases(array &$sections, ?int $investorId, array &$legacy, array &$limitations): void
    {
        $purchases = Product::query()
            ->where('trash', false)
            ->whereNotNull('variant_id')
            ->when($investorId, fn ($q) => $q->where('investor_id', $investorId), fn ($q) => $q->whereNotNull('investor_id'))
            ->get([
                'id',
                'quantity',
                'remaining_quantity',
                'purchase_currency_code',
                'purchase_unit_amount',
                'purchase_line_amount',
                'purchase_price_afn',
                'line_total',
            ]);

        foreach ($purchases as $purchase) {
            $code = $this->normalizeKnownCode($purchase->purchase_currency_code);
            if ($code === null) {
                $legacy['purchases_without_currency'] = (int) $legacy['purchases_without_currency'] + 1;
                $limitations[] = 'Some purchase rows lack purchase_currency_code and are excluded from original-currency sections.';

                continue;
            }

            $qty = max(0, (int) $purchase->quantity);
            $remaining = max(0, (int) $purchase->remaining_quantity);
            $sold = max(0, $qty - $remaining);
            $unitOriginal = (string) ($purchase->purchase_unit_amount ?? '0');
            if ((float) $unitOriginal <= 0 && $qty > 0 && $purchase->purchase_line_amount !== null) {
                $unitOriginal = Money::div((string) $purchase->purchase_line_amount, (string) $qty, 8);
            }
            $unitAfn = $qty > 0
                ? Money::div((string) ($purchase->purchase_price_afn ?? $purchase->line_total ?? '0'), (string) $qty, 8)
                : '0';

            $sections[$code]['inventory_value'] = Money::add(
                $sections[$code]['inventory_value'],
                Money::round(Money::mul($unitOriginal, (string) $remaining, 8), 2),
                2
            );
            $sections[$code]['historical_afn']['inventory_value'] = Money::add(
                $sections[$code]['historical_afn']['inventory_value'],
                Money::round(Money::mul($unitAfn, (string) $remaining, 8), 2),
                2
            );
            $sections[$code]['sold_goods_value'] = Money::add(
                $sections[$code]['sold_goods_value'],
                Money::round(Money::mul($unitOriginal, (string) $sold, 8), 2),
                2
            );
            $sections[$code]['historical_afn']['sold_goods_value'] = Money::add(
                $sections[$code]['historical_afn']['sold_goods_value'],
                Money::round(Money::mul($unitAfn, (string) $sold, 8), 2),
                2
            );
            $sections[$code]['unit_count'] += $remaining;
            $sections[$code]['sold_unit_count'] += $sold;
            $sections[$code]['batch_count']++;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $sections
     * @param  array<string, mixed>  $legacy
     */
    private function accumulateProfits(array &$sections, array &$legacy, ?int $investorId): void
    {
        $rows = InvestorProfitEntry::query()
            ->when($investorId, fn ($q) => $q->where('investor_id', $investorId))
            ->whereIn('status', ['pending', 'paid'])
            ->get([
                'currency_code',
                'profit_amount_currency',
                'profit_amount_afn',
                'status',
            ]);

        foreach ($rows as $row) {
            $code = $this->normalizeKnownCode($row->currency_code);
            $afn = Money::round((string) ($row->profit_amount_afn ?? '0'), 2);

            if ($code === null) {
                // Do not invent original currency from AFN ledger.
                $legacy['profit_afn_without_currency'] = Money::add(
                    (string) $legacy['profit_afn_without_currency'],
                    $afn,
                    2
                );

                continue;
            }

            $original = $row->profit_amount_currency !== null && $row->profit_amount_currency !== ''
                ? Money::round((string) $row->profit_amount_currency, 2)
                : ($code === 'AFN' ? $afn : null);

            if ($original === null) {
                $legacy['profit_afn_without_currency'] = Money::add(
                    (string) $legacy['profit_afn_without_currency'],
                    $afn,
                    2
                );

                continue;
            }

            $sections[$code]['investor_profit'] = Money::add($sections[$code]['investor_profit'], $original, 2);
            $sections[$code]['historical_afn']['investor_profit'] = Money::add(
                $sections[$code]['historical_afn']['investor_profit'],
                $afn,
                2
            );

            if ($row->status === 'paid') {
                $sections[$code]['paid_profit'] = Money::add($sections[$code]['paid_profit'], $original, 2);
                $sections[$code]['historical_afn']['paid_profit'] = Money::add(
                    $sections[$code]['historical_afn']['paid_profit'],
                    $afn,
                    2
                );
            } else {
                $sections[$code]['pending_profit'] = Money::add($sections[$code]['pending_profit'], $original, 2);
                $sections[$code]['historical_afn']['pending_profit'] = Money::add(
                    $sections[$code]['historical_afn']['pending_profit'],
                    $afn,
                    2
                );
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $sections
     * @param  array<string, mixed>  $legacy
     */
    private function accumulateWithdrawals(array &$sections, array &$legacy, ?int $investorId): void
    {
        $rows = InvestorWithdrawal::query()
            ->when($investorId, fn ($q) => $q->where('investor_id', $investorId))
            ->whereIn('status', ['approved', 'paid'])
            ->get([
                'currency_code',
                'amount_currency',
                'amount_afn',
            ]);

        foreach ($rows as $row) {
            $code = $this->normalizeKnownCode($row->currency_code);
            $afn = Money::round((string) ($row->amount_afn ?? '0'), 2);

            if ($code === null) {
                $legacy['withdrawals_afn_without_currency'] = Money::add(
                    (string) $legacy['withdrawals_afn_without_currency'],
                    $afn,
                    2
                );

                continue;
            }

            $original = $row->amount_currency !== null && $row->amount_currency !== ''
                ? Money::round((string) $row->amount_currency, 2)
                : ($code === 'AFN' ? $afn : null);

            if ($original === null) {
                $legacy['withdrawals_afn_without_currency'] = Money::add(
                    (string) $legacy['withdrawals_afn_without_currency'],
                    $afn,
                    2
                );

                continue;
            }

            $sections[$code]['withdrawals'] = Money::add($sections[$code]['withdrawals'], $original, 2);
            $sections[$code]['historical_afn']['withdrawals'] = Money::add(
                $sections[$code]['historical_afn']['withdrawals'],
                $afn,
                2
            );
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $sections
     * @param  list<string>  $limitations
     */
    private function accumulateBalances(array &$sections, ?int $investorId, array &$limitations): void
    {
        $walletBalances = InvestorWallet::query()
            ->when($investorId, fn ($q) => $q->where('investor_id', $investorId))
            ->selectRaw('COALESCE(SUM(capital_balance_afn), 0) as capital, COALESCE(SUM(profit_balance_afn), 0) as profit')
            ->first();

        $capital = Money::round((string) $walletBalances->capital, 2);
        $profit = Money::round((string) $walletBalances->profit, 2);

        $sections['AFN']['current_balance'] = Money::round(Money::add($capital, $profit, 8), 2);
        $sections['AFN']['current_balance_available'] = true;
        $sections['AFN']['metrics_available']['current_balance'] = true;

        $cashBalances = Investment::notTrashed()
            ->when($investorId, fn ($q) => $q->where('investor_id', $investorId))
            ->where('source_type', 'cash')
            ->whereIn('currency_code', ['USD', 'AED'])
            ->selectRaw("currency_code, COALESCE(SUM(CASE WHEN status IN ('active', 'partially_used') THEN available_amount_currency ELSE 0 END), 0) as available_cash, COUNT(*) as cash_tracks")
            ->groupBy('currency_code')
            ->get()
            ->keyBy('currency_code');

        foreach (['USD', 'AED'] as $code) {
            $cashBalance = $cashBalances->get($code);
            $availableCash = $cashBalance?->available_cash ?? '0';

            $pendingProfit = $sections[$code]['pending_profit'];
            $hasCashTrack = (int) ($cashBalance?->cash_tracks ?? 0) > 0;

            if (! $hasCashTrack && (float) $pendingProfit == 0.0) {
                $sections[$code]['current_balance'] = null;
                $sections[$code]['current_balance_available'] = false;
                $sections[$code]['metrics_available']['current_balance'] = false;
                $limitations[] = "{$code} current balance is unavailable because the AFN wallet cannot be reverse-engineered into original {$code} without inventing rates.";

                continue;
            }

            $sections[$code]['current_balance'] = Money::round(
                Money::add((string) ($availableCash ?: '0'), (string) $pendingProfit, 8),
                2
            );
            $sections[$code]['current_balance_available'] = true;
            $sections[$code]['metrics_available']['current_balance'] = true;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildExchangeValuation(?int $investorId): array
    {
        $batches = $this->investorBatches($investorId);
        $byCurrency = [];
        foreach (OriginalCurrencyMoney::CODES as $code) {
            $byCurrency[$code] = [
                'code' => $code,
                'original_amount' => '0.00',
                'historical_afn' => '0.00',
                'units' => 0,
                'batches' => 0,
                'weighted_average_fx_to_afn' => null,
                'kind' => 'historical_weighted_average',
                'available' => false,
            ];
        }

        $rowsByCurrency = [
            'USD' => [],
            'AED' => [],
            'AFN' => [],
        ];

        foreach ($batches as $batch) {
            $code = $this->normalizeKnownCode($batch->purchaseItem?->purchase_currency_code);
            if ($code === null) {
                continue;
            }

            $remainingQty = max(0, (int) $batch->remaining_quantity);
            if ($remainingQty <= 0) {
                continue;
            }

            $unitOriginal = (string) ($batch->unit_cost_currency
                ?? $batch->purchaseItem?->purchase_unit_amount
                ?? '0');
            $unitAfn = (string) ($batch->unit_cost_afn ?? '0');
            $original = Money::round(Money::mul($unitOriginal, (string) $remainingQty, 8), 2);
            $afn = Money::round(Money::mul($unitAfn, (string) $remainingQty, 8), 2);

            $byCurrency[$code]['original_amount'] = Money::add($byCurrency[$code]['original_amount'], $original, 2);
            $byCurrency[$code]['historical_afn'] = Money::add($byCurrency[$code]['historical_afn'], $afn, 2);
            $byCurrency[$code]['units'] += $remainingQty;
            $byCurrency[$code]['batches']++;

            $rowsByCurrency[$code][] = [
                'original_amount' => $original,
                'afn_amount' => $afn,
                'quantity' => $remainingQty,
            ];
        }

        foreach (['USD', 'AED'] as $code) {
            $avg = $this->weightedAverageFromRemaining($rowsByCurrency[$code]);
            $byCurrency[$code]['weighted_average_fx_to_afn'] = $avg['rate'];
            $byCurrency[$code]['available'] = $avg['available'];
            $byCurrency[$code]['kind'] = 'historical_weighted_average';
        }

        // AFN inventory has rate 1 by definition.
        if ((float) $byCurrency['AFN']['original_amount'] > 0) {
            $byCurrency['AFN']['weighted_average_fx_to_afn'] = '1.00000000';
            $byCurrency['AFN']['available'] = true;
            $byCurrency['AFN']['kind'] = 'ledger';
        }

        $totalUnits = array_sum(array_map(fn ($row) => (int) $row['units'], $byCurrency));
        $totalBatches = array_sum(array_map(fn ($row) => (int) $row['batches'], $byCurrency));

        return [
            'calculation_date' => Carbon::now()->toDateTimeString(),
            'basis' => 'historical_batch_cost',
            'basis_label' => 'Historical weighted average from remaining batch costs (not current market FX)',
            'total_afn_inventory_cost' => Money::round(
                Money::add(
                    Money::add($byCurrency['AFN']['historical_afn'], $byCurrency['USD']['historical_afn'], 8),
                    $byCurrency['AED']['historical_afn'],
                    8
                ),
                2
            ),
            'inventory_by_currency' => $byCurrency,
            'weighted_average_usd_afn' => $byCurrency['USD']['weighted_average_fx_to_afn'],
            'weighted_average_aed_afn' => $byCurrency['AED']['weighted_average_fx_to_afn'],
            'batches_included' => $totalBatches,
            'units_included' => $totalUnits,
            'note' => 'AFN inventory cost totals may be summed because they share the ledger currency. Original USD and AED amounts must never be added to AFN or to each other.',
        ];
    }

    /**
     * @return Collection<int, ProductBatch>
     */
    private function investorBatches(?int $investorId): Collection
    {
        return ProductBatch::query()
            ->with(['purchaseItem:id,purchase_currency_code,purchase_unit_amount,purchase_price_afn,line_total,quantity'])
            ->where('trash', false)
            ->when(
                $investorId,
                fn ($q) => $q->where('investor_id', $investorId),
                fn ($q) => $q->whereNotNull('investor_id')
            )
            ->get([
                'id',
                'purchase_item_id',
                'investor_id',
                'quantity',
                'remaining_quantity',
                'unit_cost_currency',
                'unit_cost_afn',
                'exchange_rate',
            ]);
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    private function finalizeSection(array $section): array
    {
        foreach ([
            'cash_capital',
            'goods_purchase_value',
            'inventory_value',
            'sold_goods_value',
            'investor_profit',
            'paid_profit',
            'pending_profit',
            'withdrawals',
        ] as $key) {
            $section[$key] = Money::round((string) $section[$key], 2);
        }

        foreach ($section['historical_afn'] as $key => $value) {
            $section['historical_afn'][$key] = Money::round((string) $value, 2);
        }

        if ($section['current_balance'] !== null) {
            $section['current_balance'] = Money::round((string) $section['current_balance'], 2);
        }

        return $section;
    }

    private function normalizeKnownCode(?string $code): ?string
    {
        $meta = OriginalCurrencyMoney::resolveCode($code);

        if (! $meta['known'] || $meta['code'] === null) {
            return null;
        }

        $normalized = strtoupper($meta['code']);

        return in_array($normalized, OriginalCurrencyMoney::CODES, true) ? $normalized : null;
    }
}
