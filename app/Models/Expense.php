<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'expenses';

    protected $fillable = [
        'branch_id',
        'expense_number',
        'title',
        'category',
        'currency_id',
        'currency_code',
        'currency_symbol',
        'amount_currency',
        'exchange_rate',
        'rate_effective_at',
        'amount_afn',
        'expense_date',
        'payment_method',
        'description',
        'created_by',
        'user_id',
        'trash',
    ];

    protected $casts = [
        'amount_currency' => 'decimal:2',
        'exchange_rate' => 'decimal:8',
        'rate_effective_at' => 'datetime',
        'amount_afn' => 'decimal:2',
        'expense_date' => 'date',
        'trash' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public const CATEGORIES = [
        'rent' => 'Rent',
        'salary' => 'Salary',
        'electricity' => 'Electricity',
        'water' => 'Water',
        'internet' => 'Internet',
        'transport' => 'Transport',
        'supplies' => 'Supplies',
        'marketing' => 'Marketing',
        'maintenance' => 'Maintenance',
        'tax' => 'Tax',
        'other' => 'Other',
    ];

    public const PAYMENT_METHODS = [
        'cash' => 'Cash',
        'card' => 'Card',
        'bank' => 'Bank Transfer',
        'other' => 'Other',
    ];

    /* Relationships */

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function saleReturnItems(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class, 'expense_id');
    }

    /* Scopes */

    public function scopeNotTrashed($query)
    {
        return $query->where('trash', false);
    }

    /**
     * SoftDeletes also defines onlyTrashed() for deleted_at.
     * This app uses a trash flag for expenses — use trashFlagged() for that set.
     */
    public function scopeTrashFlagged($query)
    {
        return $query->withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class)
            ->where('trash', true);
    }

    /** @deprecated Prefer trashFlagged(); kept for existing controllers. */
    public function scopeOnlyTrashed($query)
    {
        return $query->trashFlagged();
    }

    /* Helpers */

    public static function generateExpenseNumber(): string
    {
        $prefix = 'EXP-'.date('Ymd');
        $last = self::where('expense_number', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->value('expense_number');

        if ($last) {
            $lastNum = (int) substr($last, -4);

            return $prefix.'-'.str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
        }

        return $prefix.'-0001';
    }
}
