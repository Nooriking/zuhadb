<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    use HasFactory;

    protected $table = 'product_prices';

    protected $fillable = [
        'product_id',
        'variant_id',
        'price_type',
        'currency_id',
        'currency_code',
        'amount',
        'min_qty',
        'is_active',
        'valid_from',
        'valid_to',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'min_qty' => 'integer',
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const TYPES = [
        'purchase' => 'Purchase',
        'sale' => 'Sale',
        'wholesale' => 'Wholesale',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('price_type', $type);
    }

    public function scopeForCurrencyCode($query, string $code)
    {
        return $query->where('currency_code', strtoupper($code));
    }
}
