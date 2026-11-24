<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Support\FxBoard;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CurrencyService
{
    /**
     * Snapshot of the ledger currency for a sale or payment.
     *
     * POS amounts are stored in AFN. This snapshot records that fact on the
     * document so later default-currency changes cannot rewrite history.
     *
     * @return array{ledger_currency_code: string, ledger_currency_symbol: string|null, ledger_decimal_places: int}
     */
    public function ledgerSnapshot(): array
    {
        $ledger = Currency::ledger();

        return [
            'ledger_currency_code' => $ledger?->code ?? (string) config('business.default_currency', 'AFN'),
            'ledger_currency_symbol' => $ledger?->symbol,
            'ledger_decimal_places' => (int) ($ledger?->decimal_places ?? 2),
        ];
    }

    /**
     * Convert an amount from one catalog currency into another using
     * rate_to_default: 1 unit of the currency = rate_to_default units of the default.
     */
    public function convert(string $amount, Currency $from, Currency $to, ?int $decimals = null): string
    {
        if ($from->id === $to->id) {
            return Money::round($amount, $decimals ?? $to->decimal_places);
        }

        if (! $this->hasPositiveRate($from) || ! $this->hasPositiveRate($to)) {
            throw new \InvalidArgumentException('A positive exchange rate is required for conversion.');
        }

        $inDefault = Money::mul((string) $amount, (string) $from->rate_to_default, 12);
        $converted = Money::div($inDefault, (string) $to->rate_to_default, 12);

        return Money::round($converted, $decimals ?? $to->decimal_places);
    }

    public function hasPositiveRate(Currency $currency): bool
    {
        return (float) $currency->rate_to_default > 0;
    }

    /**
     * Units of the ledger (AFN) equal to 1 unit of $from.
     * Example: if 1 USD = 70 AFN this returns "70.00000000".
     *
     * Prefers the latest operational exchange_rates row at $at when present,
     * otherwise falls back to the catalog rate_to_default.
     */
    public function rateToLedger(Currency $from, CarbonInterface|\DateTimeInterface|string|null $at = null): string
    {
        $ledger = Currency::ledger() ?? $from;

        if ($from->id === $ledger->id) {
            return '1.00000000';
        }

        $resolved = $this->resolveAt($from, $ledger, $at, allowCatalogFallback: true);

        return Money::round((string) $resolved['rate'], 8);
    }

    /**
     * Resolve the FX rate from $from → $to at an effective timestamp.
     * Never uses a future rate. Rejects zero/negative rates.
     *
     * @return array{rate: string, kind: string, effective_at: string|null, exchange_rate_id: int|null}
     */
    public function resolveAt(
        Currency $from,
        Currency $to,
        CarbonInterface|\DateTimeInterface|string|null $at = null,
        bool $allowCatalogFallback = true,
        bool $allowMissing = false,
    ): array {
        if ($from->id === $to->id) {
            return [
                'rate' => '1.00000000',
                'kind' => 'ledger',
                'effective_at' => null,
                'exchange_rate_id' => null,
            ];
        }

        $atCarbon = $at
            ? \Illuminate\Support\Carbon::parse($at)
            : now();

        if (Schema::hasTable('exchange_rates')) {
            $row = ExchangeRate::query()
                ->notTrashed()
                ->where('from_currency_id', $from->id)
                ->where('to_currency_id', $to->id)
                ->where('effective_at', '<=', $atCarbon)
                ->where('rate', '>', 0)
                ->orderByDesc('effective_at')
                ->orderByDesc('id')
                ->first();

            if ($row) {
                return [
                    'rate' => Money::round((string) $row->rate, 8),
                    'kind' => 'operational',
                    'effective_at' => optional($row->effective_at)?->toDateTimeString(),
                    'exchange_rate_id' => $row->id,
                ];
            }

            $inverse = ExchangeRate::query()
                ->notTrashed()
                ->where('from_currency_id', $to->id)
                ->where('to_currency_id', $from->id)
                ->where('effective_at', '<=', $atCarbon)
                ->where('rate', '>', 0)
                ->orderByDesc('effective_at')
                ->orderByDesc('id')
                ->first();

            if ($inverse) {
                $rate = Money::div('1', (string) $inverse->rate, 12);

                return [
                    'rate' => Money::round($rate, 8),
                    'kind' => 'operational_inverse',
                    'effective_at' => optional($inverse->effective_at)?->toDateTimeString(),
                    'exchange_rate_id' => $inverse->id,
                ];
            }
        }

        if ($allowCatalogFallback && $this->canConvert($from, $to)) {
            $rate = $this->convert('1', $from, $to, 8);

            return [
                'rate' => $rate,
                'kind' => 'catalog',
                'effective_at' => null,
                'exchange_rate_id' => null,
            ];
        }

        if ($allowMissing) {
            return [
                'rate' => '0',
                'kind' => 'missing',
                'effective_at' => null,
                'exchange_rate_id' => null,
            ];
        }

        throw ValidationException::withMessages([
            'exchange_rate' => "No applicable exchange rate for {$from->code} → {$to->code} at {$atCarbon->toDateTimeString()}. Enter Product Reference Rates or post a rate on the Exchange Rates page (and set catalog rate_to_default > 0).",
        ]);
    }

    /**
     * Convert using resolveAt (historical-safe when operational rates exist).
     */
    public function convertAt(
        string $amount,
        Currency $from,
        Currency $to,
        CarbonInterface|\DateTimeInterface|string|null $at = null,
        ?int $decimals = null,
        bool $allowCatalogFallback = true,
    ): string {
        if ($from->id === $to->id) {
            return Money::round($amount, $decimals ?? $to->decimal_places);
        }

        $resolved = $this->resolveAt($from, $to, $at, $allowCatalogFallback);

        return Money::round(Money::mul($amount, $resolved['rate'], 12), $decimals ?? $to->decimal_places);
    }

    /**
     * Post an operational rate and sync the catalog current rate when converting into the ledger/default.
     */
    public function postRate(
        Currency $from,
        Currency $to,
        string $rate,
        CarbonInterface|\DateTimeInterface|string $effectiveAt,
        ?int $createdBy = null,
        string $source = 'manual',
    ): ExchangeRate {
        if ((float) $rate <= 0) {
            throw ValidationException::withMessages([
                'rate' => 'Exchange rate must be greater than zero.',
            ]);
        }

        if ($from->id === $to->id) {
            throw ValidationException::withMessages([
                'to_currency_id' => 'Source and target currencies must be different.',
            ]);
        }

        return DB::transaction(function () use ($from, $to, $rate, $effectiveAt, $createdBy, $source) {
            $row = ExchangeRate::create([
                'from_currency_id' => $from->id,
                'to_currency_id' => $to->id,
                'rate' => Money::round($rate, 8),
                'effective_at' => \Illuminate\Support\Carbon::parse($effectiveAt),
                'source' => $source,
                'created_by' => $createdBy ?? Auth::id(),
                'trash' => false,
            ]);

            $ledger = Currency::ledger();
            $default = Currency::getDefault();

            // Keep catalog current rate in sync for convenience conversions.
            if ($ledger && $to->id === $ledger->id) {
                $from->update([
                    'rate_to_default' => Money::round($rate, 8),
                    'user_id' => $createdBy ?? Auth::id(),
                ]);
            } elseif ($default && $to->id === $default->id) {
                $from->update([
                    'rate_to_default' => Money::round($rate, 8),
                    'user_id' => $createdBy ?? Auth::id(),
                ]);
            }

            return $row;
        });
    }

    /**
     * Post the Central Exchange Board base pairs (USD→AFN, AED→AFN).
     * USD→AED is never stored — it is always derived as usd_to_afn ÷ aed_to_afn.
     *
     * @return array{
     *     usd_to_afn: ExchangeRate,
     *     aed_to_afn: ExchangeRate,
     *     usd_to_aed: string,
     *     board: FxBoard
     * }
     */
    public function postBoardRates(
        string $usdToAfn,
        string $aedToAfn,
        CarbonInterface|\DateTimeInterface|string|null $effectiveAt = null,
        ?int $createdBy = null,
        string $source = 'manual',
    ): array {
        if ((float) $usdToAfn <= 0) {
            throw ValidationException::withMessages([
                'usd_to_afn' => 'USD→AFN rate must be greater than zero.',
            ]);
        }
        if ((float) $aedToAfn <= 0) {
            throw ValidationException::withMessages([
                'aed_to_afn' => 'AED→AFN rate must be greater than zero.',
            ]);
        }

        $ledger = Currency::ledger();
        $usd = Currency::query()->notTrashed()->where('code', 'USD')->first();
        $aed = Currency::query()->notTrashed()->where('code', 'AED')->first();

        if (! $ledger || ! $usd || ! $aed) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'AFN, USD, and AED currencies must exist before posting board rates.',
            ]);
        }

        $at = $effectiveAt ?? now();

        return DB::transaction(function () use ($usd, $aed, $ledger, $usdToAfn, $aedToAfn, $at, $createdBy, $source) {
            $usdRow = $this->postRate($usd, $ledger, (string) $usdToAfn, $at, $createdBy, $source);
            $aedRow = $this->postRate($aed, $ledger, (string) $aedToAfn, $at, $createdBy, $source);

            $board = FxBoard::fromArray([
                'usd_to_afn' => (string) $usdRow->rate,
                'aed_to_afn' => (string) $aedRow->rate,
                'effective_at' => optional($usdRow->effective_at)?->toIso8601String()
                    ?? optional($aedRow->effective_at)?->toIso8601String(),
                'source' => $source,
                'kind' => 'operational',
            ]);

            try {
                app(ExchangeRateService::class)->forget();
            } catch (\Throwable) {
                // Cache clear is best-effort.
            }

            return [
                'usd_to_afn' => $usdRow,
                'aed_to_afn' => $aedRow,
                'usd_to_aed' => $board->usdToAed,
                'board' => $board,
            ];
        });
    }

    /**
     * Resolve the AFN-based FX board at a timestamp.
     * Base rates: USD→AFN and AED→AFN. USD→AED is always derived as usd/aed.
     *
     * Overrides (when authorized upstream) may supply either base rate explicitly.
     *
     * @param  array{usd_to_afn?: string|float|null, aed_to_afn?: string|float|null, source?: string|null}  $overrides
     */
    public function boardAt(
        CarbonInterface|\DateTimeInterface|string|null $at = null,
        array $overrides = [],
        bool $allowCatalogFallback = true,
    ): FxBoard {
        $ledger = Currency::ledger();
        $usd = Currency::query()->notTrashed()->where('code', 'USD')->first();
        $aed = Currency::query()->notTrashed()->where('code', 'AED')->first();

        if (! $ledger || ! $usd || ! $aed) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'AFN, USD, and AED currencies must exist before posting multi-currency transactions.',
            ]);
        }

        if (array_key_exists('usd_to_afn', $overrides) && $overrides['usd_to_afn'] !== null && $overrides['usd_to_afn'] !== '') {
            if ((float) $overrides['usd_to_afn'] <= 0) {
                throw ValidationException::withMessages([
                    'usd_to_afn_rate' => 'USD→AFN rate must be greater than zero.',
                ]);
            }
        }
        if (array_key_exists('aed_to_afn', $overrides) && $overrides['aed_to_afn'] !== null && $overrides['aed_to_afn'] !== '') {
            if ((float) $overrides['aed_to_afn'] <= 0) {
                throw ValidationException::withMessages([
                    'aed_to_afn_rate' => 'AED→AFN rate must be greater than zero.',
                ]);
            }
        }
        if (array_key_exists('usd_to_aed', $overrides) && $overrides['usd_to_aed'] !== null && $overrides['usd_to_aed'] !== '') {
            if ((float) $overrides['usd_to_aed'] <= 0) {
                throw ValidationException::withMessages([
                    'usd_to_aed_rate' => 'USD→AED rate must be greater than zero.',
                ]);
            }
        }

        $overrideUsd = isset($overrides['usd_to_afn']) && (float) $overrides['usd_to_afn'] > 0
            ? Money::round((string) $overrides['usd_to_afn'], 8)
            : null;
        $overrideAed = isset($overrides['aed_to_afn']) && (float) $overrides['aed_to_afn'] > 0
            ? Money::round((string) $overrides['aed_to_afn'], 8)
            : null;

        // Product forms store USD→AED (AED per 1 USD). Derive AED→AFN when needed:
        // 1 AED = (USD→AFN) / (USD→AED) AFN.
        if ($overrideAed === null && $overrideUsd !== null
            && isset($overrides['usd_to_aed']) && (float) $overrides['usd_to_aed'] > 0) {
            $overrideAed = Money::round(Money::div($overrideUsd, (string) $overrides['usd_to_aed'], 12), 8);
        }

        $atStamp = $at
            ? \Illuminate\Support\Carbon::parse($at)->toDateTimeString()
            : now()->toDateTimeString();

        try {
            $usdResolved = $overrideUsd !== null
                ? ['rate' => $overrideUsd, 'kind' => 'transaction', 'effective_at' => $atStamp, 'exchange_rate_id' => null]
                : $this->resolveAt($usd, $ledger, $at, $allowCatalogFallback);

            $aedResolved = $overrideAed !== null
                ? ['rate' => $overrideAed, 'kind' => 'transaction', 'effective_at' => $atStamp, 'exchange_rate_id' => null]
                : $this->resolveAt($aed, $ledger, $at, $allowCatalogFallback);
        } catch (ValidationException $e) {
            $messages = $e->errors();
            if (isset($messages['exchange_rate'])) {
                throw ValidationException::withMessages([
                    'exchange_rate' => 'No FX board rate for USD/AED → AFN on this date, and catalog rates are missing. Enter Product Reference Rates (USD→AFN and USD→AED), or post rates on the Exchange Rates page.',
                    'usd_to_afn_rate' => 'Required when the FX board has no USD→AFN / AED→AFN rate.',
                    'usd_to_aed_rate' => 'Required when converting AED (used with USD→AFN to derive AED→AFN).',
                ]);
            }

            throw $e;
        }

        // Last chance: derive AED→AFN from resolved USD→AFN + override USD→AED.
        if ((float) $aedResolved['rate'] <= 0 && (float) $usdResolved['rate'] > 0
            && isset($overrides['usd_to_aed']) && (float) $overrides['usd_to_aed'] > 0) {
            $aedResolved = [
                'rate' => Money::round(Money::div((string) $usdResolved['rate'], (string) $overrides['usd_to_aed'], 12), 8),
                'kind' => 'derived',
                'effective_at' => $usdResolved['effective_at'] ?? $atStamp,
                'exchange_rate_id' => null,
            ];
        }

        if ((float) $usdResolved['rate'] <= 0 || (float) $aedResolved['rate'] <= 0) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'USD→AFN and AED→AFN rates must be greater than zero. Enter Product Reference Rates or post them on the Exchange Rates page.',
                'usd_to_afn_rate' => 'Enter how many AFN equal 1 USD.',
                'usd_to_aed_rate' => 'Enter how many AED equal 1 USD.',
            ]);
        }

        $kind = ($overrideUsd !== null || $overrideAed !== null)
            ? 'transaction'
            : (($usdResolved['kind'] === 'catalog' || $aedResolved['kind'] === 'catalog') ? 'catalog' : 'operational');

        $effective = $usdResolved['effective_at'] ?? $aedResolved['effective_at'] ?? (
            $at ? \Illuminate\Support\Carbon::parse($at)->toDateTimeString() : now()->toDateTimeString()
        );

        return FxBoard::fromArray([
            'usd_to_afn' => (string) $usdResolved['rate'],
            'aed_to_afn' => (string) $aedResolved['rate'],
            'effective_at' => $effective,
            'source' => (string) ($overrides['source'] ?? $kind),
            'kind' => $kind,
        ]);
    }

    /**
     * Payload for purchase/sale forms: current board + recent historical rates.
     *
     * @return array<string, mixed>
     */
    public function formBoardContext(CarbonInterface|\DateTimeInterface|string|null $at = null): array
    {
        $ledger = Currency::ledger();
        $usd = Currency::query()->notTrashed()->where('code', 'USD')->first();
        $aed = Currency::query()->notTrashed()->where('code', 'AED')->first();

        try {
            $board = $this->boardAt($at, [], allowCatalogFallback: true);
        } catch (\Throwable) {
            $board = null;
        }

        $history = [];
        if (Schema::hasTable('exchange_rates') && $ledger && $usd) {
            $history = ExchangeRate::query()
                ->notTrashed()
                ->where(function ($q) use ($usd, $aed, $ledger) {
                    $q->where(function ($inner) use ($usd, $ledger) {
                        $inner->where('from_currency_id', $usd->id)->where('to_currency_id', $ledger->id);
                    });
                    if ($aed) {
                        $q->orWhere(function ($inner) use ($aed, $ledger) {
                            $inner->where('from_currency_id', $aed->id)->where('to_currency_id', $ledger->id);
                        });
                    }
                })
                ->where('rate', '>', 0)
                ->orderByDesc('effective_at')
                ->orderByDesc('id')
                ->limit(30)
                ->get(['id', 'from_currency_id', 'to_currency_id', 'rate', 'effective_at', 'source'])
                ->map(fn (ExchangeRate $row) => [
                    'id' => $row->id,
                    'from_currency_id' => $row->from_currency_id,
                    'to_currency_id' => $row->to_currency_id,
                    'rate' => (string) $row->rate,
                    'effective_at' => optional($row->effective_at)?->toDateTimeString(),
                    'source' => $row->source,
                ])
                ->values()
                ->all();
        }

        return [
            'currencies' => [
                'afn' => $ledger ? Currency::toShared($ledger) : null,
                'usd' => $usd ? Currency::toShared($usd) : null,
                'aed' => $aed ? Currency::toShared($aed) : null,
            ],
            'board' => $board?->toSnapshot(),
            'board_kind' => $board?->kind,
            'history' => $history,
            'policy' => 'Base rates are AFN per 1 USD and AFN per 1 AED. USD/AED is derived as usd_to_afn ÷ aed_to_afn.',
        ];
    }

    /**
     * Catalog snapshot used only as a labeled conversion of an AFN ledger amount into USD.
     * Prefer transaction board snapshots when posting new sales.
     *
     * @return array{usd_to_ledger_rate: string|null, usd_equivalent_amount: string|null, fx_rate_kind: string|null}
     */
    public function saleUsdSnapshot(string $ledgerAmount): array
    {
        $usd = Currency::query()->notTrashed()->where('code', 'USD')->first();
        $ledger = Currency::ledger();

        if (! $usd || ! $ledger || ! $this->canConvert($usd, $ledger)) {
            return [
                'usd_to_ledger_rate' => null,
                'usd_equivalent_amount' => null,
                'fx_rate_kind' => null,
            ];
        }

        $rate = $this->rateToLedger($usd);

        return [
            'usd_to_ledger_rate' => $rate,
            'usd_equivalent_amount' => Money::round(Money::div($ledgerAmount, $rate, 12), 2),
            'fx_rate_kind' => 'catalog',
        ];
    }

    /**
     * Full three-currency sale snapshot from a transaction FX board.
     *
     * @return array<string, mixed>
     */
    public function saleBoardSnapshot(string $ledgerAmountAfn, FxBoard $board, Currency $saleCurrency, string $originalTotal): array
    {
        $eq = $board->equivalents($originalTotal, $saleCurrency->code, 2);

        return array_merge($board->toSnapshot(), [
            'sale_currency_id' => $saleCurrency->id,
            'sale_currency_code' => $saleCurrency->code,
            'sale_currency_symbol' => $saleCurrency->symbol,
            'sale_amount_currency' => Money::round($originalTotal, 2),
            'usd_to_ledger_rate' => $board->usdToAfn,
            'usd_equivalent_amount' => $eq['usd'],
            'aed_equivalent_amount' => $eq['aed'],
            'fx_rate_kind' => $board->kind,
            // ledger amount must already be authoritative AFN
            'total_afn_check' => Money::round($ledgerAmountAfn, 2),
        ]);
    }

    /**
     * Weighted average of applied rates: sum(rate × original_amount) / sum(original_amount).
     * Rows without a positive rate or amount are skipped. Returns null when nothing is usable.
     *
     * @param  iterable<int, array{rate: string|float|null, amount: string|float|null}>  $rows
     */
    public function weightedAverageRate(iterable $rows): ?string
    {
        $weight = '0';
        $sum = '0';

        foreach ($rows as $row) {
            $rate = (string) ($row['rate'] ?? '0');
            $amount = (string) ($row['amount'] ?? '0');
            if ((float) $rate <= 0 || (float) $amount <= 0) {
                continue;
            }
            $weight = Money::add($weight, $amount, 8);
            $piece = Money::mul($rate, $amount, 12);
            $sum = Money::add($sum, $piece, 12);
        }

        if ((float) $weight <= 0) {
            return null;
        }

        return Money::round(Money::div($sum, $weight, 12), 8);
    }

    /**
     * Preferred inventory FX average for one original currency:
     * Sum(historical AFN equivalents) / Sum(original-currency amounts).
     *
     * Mathematically equivalent to weightedAverageRate when AFN = rate × original,
     * but uses stored AFN snapshots so partial rounding differences stay faithful to ledger cost.
     *
     * @param  iterable<int, array{original_amount: string|float|null, afn_amount: string|float|null}>  $rows
     */
    public function weightedAverageFxFromEquivalents(iterable $rows): ?string
    {
        $original = '0';
        $afn = '0';

        foreach ($rows as $row) {
            $orig = (string) ($row['original_amount'] ?? '0');
            $afnPiece = (string) ($row['afn_amount'] ?? '0');
            if ((float) $orig <= 0 || (float) $afnPiece <= 0) {
                continue;
            }
            $original = Money::add($original, $orig, 8);
            $afn = Money::add($afn, $afnPiece, 8);
        }

        if ((float) $original <= 0) {
            return null;
        }

        return Money::round(Money::div($afn, $original, 12), 8);
    }

    /**
     * Investor and POS amounts are stored in the ledger currency (AFN).
     * Report them in the configured default when a valid rate exists.
     *
     * @return array{
     *     currency: array<string, mixed>,
     *     ledger: array<string, mixed>,
     *     conversion_available: bool,
     *     converts_from_ledger: bool
     * }
     */
    public function reportingContext(): array
    {
        $ledger = Currency::ledger();
        $default = Currency::getDefault();
        $fallback = [
            'code' => (string) config('business.default_currency', 'AFN'),
            'name' => 'Afghan Afghani',
            'symbol' => '؋',
            'decimal_places' => 2,
            'rate_to_default' => '1',
            'is_default' => true,
        ];

        $ledgerPayload = $ledger ? Currency::toShared($ledger) : $fallback;

        if ($default && $ledger && $this->canConvert($ledger, $default)) {
            return [
                'currency' => Currency::toShared($default),
                'ledger' => $ledgerPayload,
                'conversion_available' => true,
                'converts_from_ledger' => $default->id !== $ledger->id,
            ];
        }

        return [
            'currency' => $ledgerPayload,
            'ledger' => $ledgerPayload,
            'conversion_available' => $ledger !== null && $this->hasPositiveRate($ledger),
            'converts_from_ledger' => false,
        ];
    }

    public function canConvert(Currency $from, Currency $to): bool
    {
        if ($from->id === $to->id) {
            return true;
        }

        return $this->hasPositiveRate($from) && $this->hasPositiveRate($to);
    }

    public function toLedger(
        string $amount,
        Currency $from,
        CarbonInterface|\DateTimeInterface|string|null $at = null,
    ): string {
        $ledger = Currency::ledger() ?? $from;

        try {
            if ($at !== null || Schema::hasTable('exchange_rates')) {
                return $this->convertAt(
                    $amount,
                    $from,
                    $ledger,
                    $at,
                    (int) ($ledger->decimal_places ?? 2),
                    allowCatalogFallback: true,
                );
            }

            return $this->convert($amount, $from, $ledger, (int) ($ledger->decimal_places ?? 2));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages([
                'currency_id' => 'Set a positive catalog rate for '.$from->code.' before using it on monetary fields.',
            ]);
        }
    }

    public function requireActive(?int $id, string $field = 'currency_id'): Currency
    {
        $currency = Currency::query()->notTrashed()->find($id);

        if (! $currency) {
            throw ValidationException::withMessages([
                $field => 'The selected currency is invalid.',
            ]);
        }

        if ($currency->status !== 'active') {
            throw ValidationException::withMessages([
                $field => 'The selected currency is inactive.',
            ]);
        }

        return $currency;
    }

    /**
     * @return array{id: int, code: string, symbol: string|null, rate_to_default: string}
     */
    public function snapshot(Currency $currency): array
    {
        return [
            'id' => $currency->id,
            'code' => $currency->code,
            'symbol' => $currency->symbol,
            'rate_to_default' => (string) ($currency->rate_to_default ?? '0'),
        ];
    }

    /**
     * @param  iterable<int, array{amount: string|int|float, currency:?Currency, code?:?string, symbol?:?string}>  $rows
     * @return array{by_currency: list<array<string, mixed>>, combined: array<string, mixed>|null}
     */
    public function summarize(iterable $rows): array
    {
        $groups = [];
        $combinedDefault = '0';
        $convertible = true;
        $default = Currency::getDefault();

        foreach ($rows as $row) {
            $currency = $row['currency'] ?? null;
            $code = $currency?->code ?? ($row['code'] ?? 'UNSET');
            $symbol = $currency?->symbol ?? ($row['symbol'] ?? null);
            $amount = Money::round((string) ($row['amount'] ?? 0), 2);

            if (! isset($groups[$code])) {
                $groups[$code] = ['code' => $code, 'symbol' => $symbol, 'total' => '0'];
            }

            $groups[$code]['total'] = function_exists('bcadd')
                ? bcadd($groups[$code]['total'], $amount, 2)
                : number_format((float) $groups[$code]['total'] + (float) $amount, 2, '.', '');

            if (! $currency instanceof Currency || ! $default || ! $this->canConvert($currency, $default)) {
                $convertible = false;

                continue;
            }

            try {
                $piece = $this->convert($amount, $currency, $default, 2);
                $combinedDefault = function_exists('bcadd')
                    ? bcadd($combinedDefault, $piece, 2)
                    : number_format((float) $combinedDefault + (float) $piece, 2, '.', '');
            } catch (\Throwable) {
                $convertible = false;
            }
        }

        return [
            'by_currency' => array_values($groups),
            'combined' => $convertible && $default
                ? [
                    'code' => $default->code,
                    'name' => $default->name,
                    'symbol' => $default->symbol,
                    'total' => $combinedDefault,
                ]
                : null,
        ];
    }

    /**
     * Convert a ledger (AFN) amount into the reporting currency.
     * Returns null when no reliable rate is available.
     */
    public function fromLedger(float|string $amount): ?float
    {
        $context = $this->reportingContext();
        $ledger = Currency::ledger();
        $default = Currency::getDefault();

        if (! $context['conversion_available']) {
            return null;
        }

        if (! $context['converts_from_ledger'] || ! $ledger || ! $default) {
            return (float) Money::round((string) $amount, (int) ($context['currency']['decimal_places'] ?? 2));
        }

        try {
            return (float) $this->convert((string) $amount, $ledger, $default);
        } catch (\Throwable) {
            return null;
        }
    }

    public function reportAmount(float|string $ledgerAmount): float
    {
        $converted = $this->fromLedger($ledgerAmount);

        if ($converted === null) {
            return round((float) $ledgerAmount, 2);
        }

        return $converted;
    }

    /**
     * Convert an aggregated AFN ledger total into AFN / USD / AED display equivalents
     * using the reporting-date FxBoard (not per-transaction historical rates).
     *
     * Aggregate historical KPIs in AFN first, then call this once for display.
     * When the board cannot be resolved, AFN remains available and USD/AED are null
     * (never silently replaced with zero).
     *
     * @return array{
     *     afn: float|null,
     *     usd: float|null,
     *     aed: float|null,
     *     available: bool,
     *     basis: 'reporting_date_fx_board'|'ledger_only',
     *     board: array<string, mixed>|null
     * }
     */
    public function threeCurrencyFromLedger(
        float|string|null $ledgerAmountAfn,
        CarbonInterface|\DateTimeInterface|string|null $at = null,
    ): array {
        $afn = Money::round((string) ($ledgerAmountAfn ?? 0), 2);

        try {
            $board = $this->boardAt($at, [], allowCatalogFallback: true);
            $eq = $board->equivalents($afn, 'AFN', 2);

            return [
                'afn' => (float) $eq['afn'],
                'usd' => (float) $eq['usd'],
                'aed' => (float) $eq['aed'],
                'available' => true,
                'basis' => 'reporting_date_fx_board',
                'board' => [
                    'usd_to_afn' => $board->usdToAfn,
                    'aed_to_afn' => $board->aedToAfn,
                    'usd_to_aed' => $board->usdToAed,
                    'effective_at' => $board->effectiveAt,
                    'source' => $board->source,
                    'kind' => $board->kind,
                ],
            ];
        } catch (\Throwable) {
            return [
                'afn' => (float) $afn,
                'usd' => null,
                'aed' => null,
                'available' => false,
                'basis' => 'ledger_only',
                'board' => null,
            ];
        }
    }

    /**
     * Shared FX board metadata for dashboard charts / notes.
     *
     * @return array{
     *     available: bool,
     *     basis: 'reporting_date_fx_board'|'unavailable',
     *     usd_to_afn: string|null,
     *     aed_to_afn: string|null,
     *     usd_to_aed: string|null,
     *     effective_at: string|null,
     *     source: string|null,
     *     kind: string|null
     * }
     */
    public function dashboardFxBoard(CarbonInterface|\DateTimeInterface|string|null $at = null): array
    {
        try {
            $board = $this->boardAt($at, [], allowCatalogFallback: true);

            return [
                'available' => true,
                'basis' => 'reporting_date_fx_board',
                'usd_to_afn' => $board->usdToAfn,
                'aed_to_afn' => $board->aedToAfn,
                'usd_to_aed' => $board->usdToAed,
                'effective_at' => $board->effectiveAt,
                'source' => $board->source,
                'kind' => $board->kind,
            ];
        } catch (\Throwable) {
            return [
                'available' => false,
                'basis' => 'unavailable',
                'usd_to_afn' => null,
                'aed_to_afn' => null,
                'usd_to_aed' => null,
                'effective_at' => null,
                'source' => null,
                'kind' => null,
            ];
        }
    }

    /**
     * Map many ledger AFN amounts to three-currency payloads (one board resolution).
     *
     * @param  array<string, float|string|null>  $ledgerAmounts
     * @return array<string, array{
     *     afn: float|null,
     *     usd: float|null,
     *     aed: float|null,
     *     available: bool,
     *     basis: string,
     *     board: array<string, mixed>|null
     * }>
     */
    public function mapThreeCurrency(
        array $ledgerAmounts,
        CarbonInterface|\DateTimeInterface|string|null $at = null,
    ): array {
        $boardPayload = null;
        $board = null;

        try {
            $board = $this->boardAt($at, [], allowCatalogFallback: true);
            $boardPayload = [
                'usd_to_afn' => $board->usdToAfn,
                'aed_to_afn' => $board->aedToAfn,
                'usd_to_aed' => $board->usdToAed,
                'effective_at' => $board->effectiveAt,
                'source' => $board->source,
                'kind' => $board->kind,
            ];
        } catch (\Throwable) {
            $board = null;
        }

        $out = [];
        foreach ($ledgerAmounts as $key => $amount) {
            $afn = Money::round((string) ($amount ?? 0), 2);

            if ($board === null) {
                $out[$key] = [
                    'afn' => (float) $afn,
                    'usd' => null,
                    'aed' => null,
                    'available' => false,
                    'basis' => 'ledger_only',
                    'board' => null,
                ];

                continue;
            }

            $eq = $board->equivalents($afn, 'AFN', 2);
            $out[$key] = [
                'afn' => (float) $eq['afn'],
                'usd' => (float) $eq['usd'],
                'aed' => (float) $eq['aed'],
                'available' => true,
                'basis' => 'reporting_date_fx_board',
                'board' => $boardPayload,
            ];
        }

        return $out;
    }

    public function makeDefault(Currency $currency): Currency
    {
        if ($currency->trash || $currency->trashed()) {
            throw ValidationException::withMessages([
                'is_base' => 'A trashed currency cannot be set as the default.',
            ]);
        }

        return DB::transaction(function () use ($currency) {
            $currency = Currency::query()->lockForUpdate()->findOrFail($currency->id);
            $previous = Currency::query()
                ->where('is_base', true)
                ->where('id', '!=', $currency->id)
                ->lockForUpdate()
                ->first();

            if ($previous && (float) $currency->rate_to_default <= 0) {
                throw ValidationException::withMessages([
                    'rate_to_default' => 'The new default currency must have a positive exchange rate before it can become the default.',
                ]);
            }

            if ($previous && $previous->id !== $currency->id) {
                $this->rebaseRatesTo($currency);
            }

            Currency::query()->where('is_base', true)->update(['is_base' => false]);

            $currency->update([
                'is_base' => true,
                'status' => 'active',
                'rate_to_default' => '1.00000000',
                'user_id' => Auth::id(),
            ]);

            return $currency->refresh();
        });
    }

    /**
     * After changing the default, keep catalog rates consistent:
     * new_rate(c) = old_rate(c) / old_rate(newDefault)
     * so that 1 unit of c still equals the same real value, expressed in the new default.
     *
     * Historical sale and payment amounts are not modified.
     */
    private function rebaseRatesTo(Currency $newDefault): void
    {
        $divisor = (string) $newDefault->rate_to_default;

        Currency::query()
            ->lockForUpdate()
            ->get()
            ->each(function (Currency $row) use ($newDefault, $divisor) {
                if ($row->id === $newDefault->id) {
                    $row->rate_to_default = '1.00000000';
                } else {
                    $row->rate_to_default = Money::div((string) $row->rate_to_default, $divisor, 8);
                }
                $row->save();
            });
    }
}
