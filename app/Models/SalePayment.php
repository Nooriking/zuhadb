<?php

namespace App\Models;

use App\Services\CurrencyService;
use App\Services\ExchangeService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class SalePayment extends Model
{
    use HasFactory;

    protected $table = 'sale_payments';

    protected $fillable = [
        'sale_id',
        'customer_id',
        'amount_afn',
        'currency_id',
        'currency_code',
        'amount_currency',
        'rate_to_ledger',
        'rate_kind',
        'rate_effective_at',
        'rate_source',
        'ledger_currency_code',
        'ledger_currency_symbol',
        'ledger_decimal_places',
        'remaining_due_afn',
        'payment_method',
        'reference_number',
        'notes',
        'paid_at',
        'received_by',
    ];

    protected $casts = [
        'amount_afn'         => 'decimal:2',
        'amount_currency'    => 'decimal:2',
        'rate_to_ledger'     => 'decimal:8',
        'rate_effective_at'  => 'datetime',
        'ledger_decimal_places' => 'integer',
        'remaining_due_afn'  => 'decimal:2',
        'paid_at'            => 'datetime',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    public const METHODS = [
        'cash'   => 'Cash',
        'card'   => 'Card',
        'bank'   => 'Bank Transfer',
        'mobile' => 'Mobile Money',
        'other'  => 'Other',
    ];

    /* =========================================================
     |  Snapshot helpers (ExchangeService only — never trust UI rates)
     ========================================================= */

    /**
     * Create a payment with a fresh FX snapshot from ExchangeService.
     * Ignores any rate / amount_afn values supplied by the client.
     *
     * @param  array<string, mixed>  $data
     */
    public static function createWithSnapshot(array $data): self
    {
        $payload = static::applySnapshotFields($data);

        return static::query()->create($payload);
    }

    /**
     * Update a payment. Snapshot is refreshed only when amount_currency or currency_id change.
     * Notes / reference / method-only edits leave the original FX snapshot intact.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateWithSnapshot(array $data): self
    {
        $currencyChanged = array_key_exists('currency_id', $data)
            && (int) ($data['currency_id'] ?? 0) !== (int) ($this->currency_id ?? 0);

        $incomingAmount = $data['amount_currency']
            ?? $data['amount']
            ?? (array_key_exists('amount_afn', $data) ? $data['amount_afn'] : null);

        $amountChanged = $incomingAmount !== null
            && Money::round((string) $incomingAmount, 2) !== Money::round((string) ($this->amount_currency ?? $this->amount_afn ?? '0'), 2);

        if ($currencyChanged || $amountChanged) {
            $merged = array_merge($this->attributesToArray(), $data);
            $payload = static::applySnapshotFields($merged);
            $this->fill($payload)->save();

            return $this->fresh() ?? $this;
        }

        // Non-money fields only — preserve rate snapshot.
        unset(
            $data['rate_to_ledger'],
            $data['rate_effective_at'],
            $data['rate_source'],
            $data['rate_kind'],
            $data['amount_afn'],
            $data['exchange_rate'],
            $data['exchange_rate_to_afn'],
        );

        $this->fill($data)->save();

        return $this->fresh() ?? $this;
    }

    /**
     * Resolve currency + ExchangeService snapshot and compute amount_afn via BCMath.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function applySnapshotFields(array $data): array
    {
        // Never accept client-supplied FX or ledger amounts.
        unset(
            $data['rate_to_ledger'],
            $data['rate_effective_at'],
            $data['rate_source'],
            $data['rate_kind'],
            $data['amount_afn'],
            $data['exchange_rate'],
            $data['exchange_rate_to_afn'],
        );

        $exchange = app(ExchangeService::class);
        $currencyService = app(CurrencyService::class);
        $ledger = Currency::ledger();
        $ledgerCode = strtoupper((string) ($ledger?->code ?? config('business.default_currency', 'AFN')));

        $amountCurrency = $data['amount_currency'] ?? $data['amount'] ?? null;
        if ($amountCurrency === null || $amountCurrency === '') {
            throw ValidationException::withMessages([
                'amount_currency' => 'Payment amount is required.',
            ]);
        }

        $currencyId = $data['currency_id'] ?? null;
        $isLedger = empty($currencyId) || ($ledger && (int) $currencyId === (int) $ledger->id);

        if ($isLedger) {
            $amount = Money::round((string) $amountCurrency, 2);
            $snap = $exchange->snapshot($ledgerCode, $ledgerCode);

            $fx = [
                'currency_id' => $ledger?->id,
                'currency_code' => $ledgerCode,
                'amount_currency' => $amount,
                'amount_afn' => $amount,
                'rate_to_ledger' => Money::round((string) $snap['rate'], 8),
                'rate_kind' => 'ledger',
                'rate_effective_at' => $snap['effective_at'],
                'rate_source' => $snap['source'],
            ];
        } else {
            $currency = $currencyService->requireActive((int) $currencyId, 'currency_id');
            $fromCode = strtoupper((string) $currency->code);
            $snap = $exchange->snapshot($fromCode, $ledgerCode);
            $rate = Money::round((string) $snap['rate'], 8);
            $amount = Money::round((string) $amountCurrency, 2);
            $amountAfn = Money::round(Money::mul($amount, $rate, 12), 2);

            $fx = [
                'currency_id' => $currency->id,
                'currency_code' => $fromCode,
                'amount_currency' => $amount,
                'amount_afn' => $amountAfn,
                'rate_to_ledger' => $rate,
                'rate_kind' => 'operational',
                'rate_effective_at' => $snap['effective_at'],
                'rate_source' => $snap['source'],
            ];
        }

        unset($data['amount'], $data['amount_currency'], $data['currency_id'], $data['currency_code']);

        return array_merge($currencyService->ledgerSnapshot(), $data, $fx);
    }

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    /* =========================================================
     |  Accessors
     ========================================================= */

    public function getPaymentMethodLabelAttribute(): string
    {
        return self::METHODS[$this->payment_method] ?? ucfirst($this->payment_method ?? 'unknown');
    }
}
