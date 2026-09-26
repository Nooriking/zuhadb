<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'stock_transfers';

    protected $fillable = [
        'from_branch_id',
        'to_branch_id',
        'transfer_number',
        'transfer_date',
        'status',
        'notes',
        'created_by',
        'user_id',
        'trash',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'trash'         => 'boolean',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'deleted_at'    => 'datetime',
    ];

    /* Status Labels */

    public const STATUS_LABELS = [
        'draft'      => 'Draft',
        'in_transit' => 'In Transit',
        'completed'  => 'Completed',
        'cancelled'  => 'Cancelled',
    ];

    /* Relationships */

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class, 'stock_transfer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* Scopes */

    public function scopeNotTrashed($query)
    {
        return $query->where('trash', false);
    }

    public function scopeOnlyTrashed($query)
    {
        return $query->withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class)->where('trash', true);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /* Helpers */

    public static function generateTransferNumber(): string
    {
        $prefix = 'TRF-' . date('Ymd');
        $last = self::where('transfer_number', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->value('transfer_number');

        if ($last) {
            $lastNum = (int) substr($last, -4);
            return $prefix . '-' . str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
        }

        return $prefix . '-0001';
    }
}