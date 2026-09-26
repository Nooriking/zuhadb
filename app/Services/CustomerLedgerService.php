<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use Illuminate\Support\Facades\Auth;

class CustomerLedgerService
{
    /**
     * ثبت تراکنش فروش
     * amount_afn = مبلغ فروش جدید (فقط این فروش، نه قرض قبلی)
     * balance_after = قرض کل بعد از فروش
     */
    public function recordSale(Sale $sale): void
    {
        if (! $sale->customer_id) return;

        $customer = Customer::find($sale->customer_id);
        if (! $customer) return;

        CustomerTransaction::create([
            'customer_id'    => $sale->customer_id,
            'type'           => CustomerTransaction::TYPE_SALE,
            'amount_afn'     => (float) $sale->new_sale_amount_afn,
            'balance_after'  => (float) ($sale->grand_total_due_afn
                ?: ((float) $sale->previous_due_afn + (float) $sale->new_sale_amount_afn)),
            'reference_type' => Sale::class,
            'reference_id'   => $sale->id,
            'notes'          => "Sale {$sale->invoice_number}",
            'created_by'     => Auth::id(),
        ]);
    }

    /**
     * ثبت تراکنش پرداخت
     * amount_afn = منفی (چون قرض کم می‌شود)
     * balance_after = قرض باقی‌مانده بعد از پرداخت
     */
    public function recordPayment(SalePayment $payment): void
    {
        if (! $payment->customer_id) return;

        CustomerTransaction::create([
            'customer_id'    => $payment->customer_id,
            'type'           => CustomerTransaction::TYPE_PAYMENT,
            'amount_afn'     => -1 * (float) $payment->amount_afn,
            'balance_after'  => (float) $payment->remaining_due_afn,
            'reference_type' => SalePayment::class,
            'reference_id'   => $payment->id,
            'notes'          => $payment->sale_id
                ? "Payment for sale #{$payment->sale_id}"
                : 'Account payment',
            'created_by'     => Auth::id(),
        ]);
    }

    /**
     * ثبت تراکنش برگشتی
     * amount_afn = منفی (چون قرض کم می‌شود)
     * balance_after = قرض باقی‌مانده بعد از برگشتی
     */
    public function recordReturn(SaleReturn $saleReturn): void
    {
        if (! $saleReturn->customer_id) return;

        CustomerTransaction::create([
            'customer_id'    => $saleReturn->customer_id,
            'type'           => CustomerTransaction::TYPE_RETURN,
            'amount_afn'     => -1 * (float) $saleReturn->total_afn,
            'balance_after'  => (float) $saleReturn->new_due_afn,
            'reference_type' => SaleReturn::class,
            'reference_id'   => $saleReturn->id,
            'notes'          => "Return {$saleReturn->return_number}",
            'created_by'     => Auth::id(),
        ]);
    }

    /**
     * ثبت تعدیل دستی
     * amount_afn = مثبت (افزایش قرض) یا منفی (کاهش قرض)
     * balance_after = قرض بعد از تعدیل
     */
    public function recordAdjustment(
        Customer $customer,
        float $amountAfn,
        float $balanceAfter,
        ?string $notes = null,
    ): void {
        CustomerTransaction::create([
            'customer_id'   => $customer->id,
            'type'          => CustomerTransaction::TYPE_ADJUSTMENT,
            'amount_afn'    => $amountAfn,
            'balance_after' => $balanceAfter,
            'notes'         => $notes,
            'created_by'    => Auth::id(),
        ]);
    }

    /**
     * محاسبه قرض فعلی مشتری از روی ledger
     */
    public function calculateBalance(Customer $customer): float
    {
        return (float) CustomerTransaction::where('customer_id', $customer->id)
            ->sum('amount_afn');
    }

    /**
     * بازسازی customers.current_due_afn از روی ledger
     */
    public function rebuildCustomerBalance(Customer $customer): void
    {
        $balance = $this->calculateBalance($customer);

        $customer->update([
            'current_due_afn' => max(0, $balance),
        ]);
    }
}