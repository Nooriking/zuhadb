<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Investment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'investments';

    protected $fillable = [
        'investor_id',
        'currency_id',
        'exchange_rate',
        'amount_currency',
        'amount_afn',
        'investment_date',
        'status',
        'notes',
        'created_by',
        'trash',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:8',
        'amount_currency' => 'decimal:2',
        'amount_afn' => 'decimal:2',
        'investment_date' => 'date',
        'trash' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public const STATUS_LABELS = [
        'active' => 'Active',
        'closed' => 'Closed',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function investor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investor_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class, 'investment_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InvestorTransaction::class, 'investment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
        return $query->withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class)
            ->where('trash', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForInvestor($query, int $investorId)
    {
        return $query->where('investor_id', $investorId);
    }

    /* =========================================================
     |  Helpers
     ========================================================= */

    /**
     * Create an investment from a purchase, using today's exchange_rates board.
     *
     * @param  array{
     *     investor_id: int,
     *     currency_id: int|null,
     *     amount_currency: float|string,
     *     amount_afn?: float|string|null,
     *     exchange_rate?: float|string|null,
     *     investment_date?: string|null,
     *     notes?: string|null,
     *     created_by?: int|null
     * }  $data
     */
    public static function createFromPurchase(array $data): self
    {
        $currency = ! empty($data['currency_id'])
            ? Currency::query()->find($data['currency_id'])
            : Currency::ledger();

        $code = strtoupper((string) ($currency?->code ?? 'AFN'));
        $exchange = app(\App\Services\ExchangeService::class);

        $rate = isset($data['exchange_rate']) && (float) $data['exchange_rate'] > 0
            ? (string) $data['exchange_rate']
            : ($code === 'AFN' ? '1' : (string) $exchange->snapshot($code, 'AFN')['rate']);

        $amountCurrency = (string) ($data['amount_currency'] ?? 0);
        $amountAfn = isset($data['amount_afn']) && $data['amount_afn'] !== null
            ? (string) $data['amount_afn']
            : ($code === 'AFN'
                ? $amountCurrency
                : (string) $exchange->convert($amountCurrency, $code, 'AFN'));

        return static::create([
            'investor_id' => (int) $data['investor_id'],
            'currency_id' => $currency?->id,
            'exchange_rate' => $rate,
            'amount_currency' => $amountCurrency,
            'amount_afn' => $amountAfn,
            'investment_date' => $data['investment_date'] ?? now()->toDateString(),
            'status' => 'active',
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? auth()->id(),
            'trash' => false,
        ]);
    }

    public function close(?string $notes = null): void
    {
        $updates = ['status' => 'closed'];
        if ($notes !== null) {
            $updates['notes'] = trim(($this->notes ? $this->notes."\n" : '').$notes);
        }
        $this->update($updates);
    }
}
