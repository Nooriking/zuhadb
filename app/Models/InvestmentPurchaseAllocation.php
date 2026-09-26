<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentPurchaseAllocation extends Model
{
    use HasFactory;

    protected $table = 'investment_purchase_allocations';

    protected $fillable = [
        'investment_id',
        'purchase_id',
        'allocated_amount_afn',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'allocated_amount_afn' => 'decimal:2',
        'created_at'                => 'datetime',
        'updated_at'                => 'datetime',
    ];

    /* Relationships */

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class, 'investment_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'purchase_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}