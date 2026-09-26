<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'suppliers';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'contact_person',
        'address',
        'opening_balance',
        'notes',
        'status',
        'trash',
        'user_id',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'trash'           => 'boolean',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
        'deleted_at'      => 'datetime',
    ];

    /* Relationships */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Product::class, 'supplier_id')->whereNotNull('variant_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function openPurchases()
    {
        $paid = SupplierPaymentAllocation::query()
            ->selectRaw('product_id, SUM(amount_afn) as paid')
            ->whereNotNull('product_id')
            ->groupBy('product_id');

        return $this->purchases()
            ->notTrashed()
            ->whereIn('payment_status', ['credit', 'partial', 'unpaid'])
            ->leftJoinSub($paid, 'paid_alloc', 'paid_alloc.product_id', '=', 'products.id')
            ->select('products.*')
            ->selectRaw('COALESCE(paid_alloc.paid, 0) as paid_afn')
            ->orderBy('purchase_date')
            ->orderBy('products.id')
            ->get()
            ->map(function (Product $row) {
                $total = round((float) $row->line_total, 2);
                $paidAmount = round((float) $row->paid_afn, 2);
                $row->setAttribute('owed_afn', $total);
                $row->setAttribute('paid_afn', $paidAmount);
                $row->setAttribute('remaining_afn', round(max(0, $total - $paidAmount), 2));

                return $row;
            })
            ->filter(fn (Product $row) => (float) $row->remaining_afn > 0.009)
            ->values();
    }

    public function account(): array
    {
        $opening = round((float) $this->opening_balance, 2);
        $invoices = $this->invoices()->where('due_afn', '>', 0)->orderBy('purchase_date')->orderBy('id')->get();
        $purchaseRemaining = round((float) $invoices->sum('due_afn'), 2);

        return [
            'opening_balance' => $opening,
            'opening_remaining' => $opening,
            'purchase_remaining' => $purchaseRemaining,
            'balance' => round($opening + $purchaseRemaining, 2),
            'purchases' => $invoices,
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public static function balancesFor(iterable $ids): array
    {
        $ids = collect($ids)->filter()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $invoiceTotals = SupplierInvoice::query()
            ->whereIn('supplier_id', $ids)
            ->groupBy('supplier_id')
            ->selectRaw('supplier_id, SUM(due_afn) as due_afn, SUM(paid_afn) as paid_afn')
            ->get()
            ->keyBy('supplier_id');

        $openings = static::query()->whereIn('id', $ids)->pluck('opening_balance', 'id');

        return $ids->mapWithKeys(function ($id) use ($invoiceTotals, $openings) {
            $row = $invoiceTotals->get($id);

            return [$id => [
                'paid_afn' => round((float) ($row->paid_afn ?? 0), 2),
                'balance_afn' => round((float) ($openings[$id] ?? 0) + (float) ($row->due_afn ?? 0), 2),
            ]];
        })->all();
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

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}