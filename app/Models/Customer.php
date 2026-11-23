<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'customers';

    protected $fillable = [
        'name',
        'phone',
        'address',
        'notes',

        // Credit / Due
        'current_due_afn',
        'credit_limit_afn',
        'last_sale_at',

        // Status
        'status',
        'trash',

        // Relations
        'created_by',
    ];

    protected $casts = [
        'current_due_afn'   => 'decimal:2',
        'credit_limit_afn'  => 'decimal:2',
        'last_sale_at'      => 'datetime',
        'trash'             => 'boolean',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
        'deleted_at'        => 'datetime',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'customer_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class, 'customer_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class, 'customer_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(CustomerBalance::class, 'customer_id');
    }

    /* =========================================================
     |  Scopes
     ========================================================= */

    public function scopeNotTrashed($query)
    {
        return $query->where('trash', false);
    }

    public function scopeOnlyTrashed($query)
    {
        return $query->withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class)->where('trash', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeWithDue($query)
    {
        return $query->where('current_due_afn', '>', 0);
    }

    /* =========================================================
     |  Helpers
     ========================================================= */

    /**
     * Has the customer exceeded their credit limit?
     */
    public function hasExceededCreditLimit(): bool
    {
        if ($this->credit_limit_afn <= 0) {
            return false;
        }

        return $this->current_due_afn > $this->credit_limit_afn;
    }

    /**
     * Remaining usable credit
     */
    public function availableCreditAfn(): float
    {
        if ($this->credit_limit_afn <= 0) {
            return 0;
        }

        return max(0, (float) $this->credit_limit_afn - (float) $this->current_due_afn);
    }

    /**
     * Update current due amount (weighted AFN total)
     */
    public function updateCurrentDue(float $newBalance): void
    {
        $this->update(['current_due_afn' => $newBalance]);
    }

    /**
     * Adjust per-currency due (customer_balances) and bump denormalized current_due_afn.
     * Positive delta = customer owes more; negative = payment / credit.
     *
     * Callers should use this instead of separately incrementing current_due_afn.
     */
    public function adjustBalance(
        string $currencyCode,
        float $deltaCurrency,
        float $deltaAfn,
        ?float $rate = null,
    ): CustomerBalance {
        $balance = CustomerBalance::forCustomer((int) $this->id, $currencyCode);
        $balance->adjust($deltaCurrency, $deltaAfn, $rate);

        $this->current_due_afn = round((float) $this->current_due_afn + $deltaAfn, 2);
        $this->save();

        return $balance->fresh();
    }

    /**
     * Recalculate denormalized current_due_afn from per-currency balance_afn rows.
     */
    public function syncCurrentDueFromBalances(): void
    {
        $totalAfn = round((float) $this->balances()->sum('balance_afn'), 2);
        $this->update(['current_due_afn' => $totalAfn]);
    }

    /**
     * Due breakdown for UI: primary currency balances + AFN total.
     *
     * @return array{total_afn: float, by_currency: list<array{currency_code: string, balance_currency: float, balance_afn: float, last_rate: float|null}>}
     */
    public function dueBreakdown(): array
    {
        $rows = $this->balances()
            ->orderBy('currency_code')
            ->get(['currency_code', 'balance_currency', 'balance_afn', 'last_rate']);

        return [
            'total_afn' => round((float) $rows->sum('balance_afn'), 2),
            'by_currency' => $rows
                ->filter(fn (CustomerBalance $b) => abs((float) $b->balance_currency) > 0.009 || abs((float) $b->balance_afn) > 0.009)
                ->map(fn (CustomerBalance $b) => [
                    'currency_code' => (string) $b->currency_code,
                    'balance_currency' => round((float) $b->balance_currency, 2),
                    'balance_afn' => round((float) $b->balance_afn, 2),
                    'last_rate' => $b->last_rate !== null ? (float) $b->last_rate : null,
                ])
                ->values()
                ->all(),
        ];
    }
}