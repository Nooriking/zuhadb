<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $table = 'inventory_transactions';

    protected $fillable = [
        'branch_id',
        'variant_id',
        'batch_id',
        'type',
        'quantity',
        'before_quantity',
        'after_quantity',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity'        => 'integer',
        'before_quantity' => 'integer',
        'after_quantity'  => 'integer',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    /* =========================================================
     |  Type Labels
     ========================================================= */

    public const TYPES = [
    'purchase'     => 'Purchase',
    'sale'         => 'Sale',
    'return'       => 'Return',        // ← فقط 'return'، نه 'sale_return'
    'damage'       => 'Damage',
    'transfer_in'  => 'Transfer In',
    'transfer_out' => 'Transfer Out',
    'adjustment'   => 'Adjustment',
    'opening'      => 'Opening Stock',
];

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /* Relationships */

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo('reference');
    }

    /* Scopes */

    public function scopePurchases($query)
    {
        return $query->where('type', 'purchase');
    }

    public function scopeSales($query)
    {
        return $query->where('type', 'sale');
    }

    public function scopeReturns($query)
    {
        return $query->where('type', 'return');
    }

    public function scopeForVariant($query, int $variantId)
    {
        return $query->where('variant_id', $variantId);
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }
}