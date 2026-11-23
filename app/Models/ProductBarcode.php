<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBarcode extends Model
{
    use HasFactory;

    protected $table = 'product_barcodes';

    protected $fillable = [
        'variant_id',
        'barcode',
        'barcode_type',
        'is_primary',
        'status',
        'current_stock',   // added
    ];

    protected $casts = [
        'is_primary'    => 'boolean',
        'current_stock' => 'integer',   // added
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /* =========================================================
     |  Scopes (optional, useful)
     ========================================================= */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('current_stock', '>', 0);
    }

    /* =========================================================
     |  Helpers (optional)
     ========================================================= */

    public function getIsInStockAttribute(): bool
    {
        return $this->current_stock > 0;
    }
}