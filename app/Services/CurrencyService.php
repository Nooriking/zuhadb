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

        $inDefault = Money::mul((string) $amount, (string) $from->rate_to_default, 12);
        $converted = Money::div($inDefault, (string) $to->rate_to_default, 12);

        return Money::round($converted, $decimals ?? $to->decimal_places);
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
