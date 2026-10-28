<?php

namespace App\Services;

use App\Models\Currency;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function toLedger(string $amount, Currency $from): string
    {
        $ledger = Currency::ledger() ?? $from;

        try {
            return $this->convert($amount, $from, $ledger, (int) ($ledger->decimal_places ?? 2));
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
