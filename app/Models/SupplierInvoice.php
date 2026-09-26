<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoice extends Model
{
    use HasFactory;
    protected $fillable = [
        'supplier_id',
        'invoice_no',
        'purchase_date',
        'exchange_rate',
        'total_afn',
        'paid_afn',
        'due_afn',
        'notes',
        'bill_image',
        'created_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'exchange_rate' => 'decimal:2',
        'total_afn' => 'decimal:2',
        'paid_afn' => 'decimal:2',
        'due_afn' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function takePayment(float $amount, string $date, float $exchangeRate, ?string $notes, ?int $userId): ?SupplierPayment
    {
        $due = round((float) $this->due_afn, 2);
        $apply = round(min($amount, $due), 2);
        if ($apply <= 0) {
            return null;
        }

        $this->update([
            'paid_afn' => round((float) $this->paid_afn + $apply, 2),
            'due_afn' => round(max(0, $due - $apply), 2),
        ]);

        $balance = round((float) static::query()->where('supplier_id', $this->supplier_id)->sum('due_afn'), 2);

        return SupplierPayment::create([
            'supplier_id' => $this->supplier_id,
            'receipt_number' => SupplierPayment::nextReceiptNumber(),
            'amount_afn' => $apply,
            'balance_after' => $balance,
            'payment_date' => $date,
            'exchange_rate' => round($exchangeRate, 2),
            'payment_method' => 'cash',
            'notes' => trim(($notes ? $notes.' ' : '').$this->invoice_no),
            'user_id' => $userId,
        ]);
    }

    public static function syncFromProduct(Product $product): void
    {
        if (! $product->supplier_id || ! $product->variant_id) {
            return;
        }

        $invoiceNo = mb_substr($product->invoice_number ?: ('P-'.$product->id), 0, 50);
        $date = optional($product->purchase_date)->toDateString() ?: now()->toDateString();

        if ($product->trash) {
            static::query()
                ->where('supplier_id', $product->supplier_id)
                ->where('invoice_no', $invoiceNo)
                ->whereDate('purchase_date', $date)
                ->where('paid_afn', '<=', 0)
                ->delete();

            return;
        }

        $total = round((float) $product->line_total, 2);
        $existing = static::query()
            ->where('supplier_id', $product->supplier_id)
            ->where('invoice_no', $invoiceNo)
            ->whereDate('purchase_date', $date)
            ->first();
        $paid = min($total, round((float) ($existing->paid_afn ?? 0), 2));

        static::query()->updateOrCreate(
            [
                'supplier_id' => $product->supplier_id,
                'invoice_no' => $invoiceNo,
                'purchase_date' => $date,
            ],
            [
                'exchange_rate' => round((float) ($product->exchange_rate ?? 0), 2),
                'total_afn' => $total,
                'paid_afn' => $paid,
                'due_afn' => round(max(0, $total - $paid), 2),
                'notes' => $product->notes,
                'bill_image' => $existing->bill_image ?? $product->bill_image,
                'created_by' => $existing->created_by ?? $product->user_id,
            ]
        );
    }
}
