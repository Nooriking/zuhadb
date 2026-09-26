<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvestorWithdrawal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'investor_withdrawals';

    protected $fillable = [
        'investor_id',
        'investment_id',
        'withdrawal_number',
        'type',
        'amount_afn',
        'balance_before_afn',
        'balance_after_afn',
        'withdrawal_date',
        'status',
        'notes',
        'approved_by',
        'approved_at',
        'paid_by',
        'paid_at',
        'created_by',
    ];

    protected $casts = [
        'amount_afn'          => 'decimal:2',
        'balance_before_afn'  => 'decimal:2',
        'balance_after_afn'   => 'decimal:2',
        'withdrawal_date'     => 'date',
        'approved_at'         => 'datetime',
        'paid_at'             => 'datetime',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
        'deleted_at'          => 'datetime',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class, 'investor_id');
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class, 'investment_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* =========================================================
     |  Scopes
     ========================================================= */

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeProfitType($query)
    {
        return $query->where('type', 'profit');
    }

    public function scopeCapitalType($query)
    {
        return $query->where('type', 'capital');
    }

    /* =========================================================
     |  Helpers
     ========================================================= */

    public static function generateWithdrawalNumber(): string
    {
        $prefix = 'WD-' . date('Ymd');
        $last = self::where('withdrawal_number', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->value('withdrawal_number');

        if ($last) {
            $lastNum = (int) substr($last, -4);
            return $prefix . '-' . str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
        }

        return $prefix . '-0001';
    }

    public function isProfitWithdrawal(): bool
    {
        return $this->type === 'profit';
    }

    public function isCapitalWithdrawal(): bool
    {
        return $this->type === 'capital';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function approve(?int $userId = null): void
    {
        $this->update([
            'status'      => 'approved',
            'approved_by' => $userId ?? auth()->id(),
            'approved_at' => now(),
        ]);
    }

    public function markAsPaid(?int $userId = null): void
    {
        $this->update([
            'status'  => 'paid',
            'paid_by' => $userId ?? auth()->id(),
            'paid_at' => now(),
        ]);
    }

    public function reject(?string $reason = null): void
    {
        $this->update([
            'status' => 'rejected',
            'notes'  => $reason ?? $this->notes,
        ]);
    }
}