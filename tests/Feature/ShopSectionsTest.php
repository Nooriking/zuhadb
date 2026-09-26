<?php

use App\Models\ActivityNotification;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorProfitEntry;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductPriceHistory;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;

test('price history stores a purchase and sale price change', function () {
    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 2000,
        'sale_price_afn' => 3000,
    ]);

    $row = ProductPriceHistory::record($variant->id, 2000, 2500, 3000, 3600, 'Add stock');

    expect($row)->not->toBeNull()
        ->and($row->change_type)->toBe('both')
        ->and((float) $row->new_purchase_price_afn)->toBe(2500.0)
        ->and((float) $row->new_sale_price_afn)->toBe(3600.0);
});

test('selling investor stock credits the investor in dollars', function () {
    $investor = Investor::factory()->create();
    $investment = Investment::factory()->create([
        'investor_id' => $investor->id,
        'capital_amount_afn' => 10000,
        'available_amount_afn' => 10000,
    ]);
    $branch = Branch::factory()->create();
    $variant = ProductVariant::factory()->create();
    $customer = Customer::factory()->create();

    ProductBatch::factory()->create([
        'branch_id' => $branch->id,
        'variant_id' => $variant->id,
        'investor_id' => $investor->id,
        'quantity' => 4,
        'remaining_quantity' => 4,
        'profit_per_unit_afn' => 10,
    ]);

    $sale = Sale::create([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'S-TEST-PROFIT',
        'subtotal_afn' => 4000,
        'discount_afn' => 0,
        'total_afn' => 4000,
        'paid_afn' => 4000,
        'due_afn' => 0,
        'sale_date' => now()->toDateString(),
        'payment_status' => 'paid',
        'status' => 'completed',
        'trash' => false,
    ]);
    $item = SaleItem::create([
        'sale_id' => $sale->id,
        'variant_id' => $variant->id,
        'quantity' => 2,
        'sale_price_afn' => 2000,
        'line_total_afn' => 4000,
        'investor_profit_afn' => 20,
    ]);

    InvestorProfitEntry::recordProfit(
        investorId: $investor->id,
        investmentId: $investment->id,
        productAllocationId: null,
        saleId: $sale->id,
        saleItemId: $item->id,
        quantity: 2,
        profitPerUnitAfn: 10,
        notes: 'Test sale profit',
    );

    $investment->refresh();
    $investor->load('wallet');

    expect((float) $investment->profit_amount_afn)->toBe(20.0)
        ->and((float) $investor->wallet->profit_balance_afn)->toBe(20.0)
        ->and(InvestorProfitEntry::query()->where('investor_id', $investor->id)->count())->toBe(1);
});

test('two pieces left notifies each admin once', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => 'active', 'trash' => false]);
    $branch = Branch::factory()->create();
    $variant = ProductVariant::factory()->create();

    Inventory::adjust($branch->id, $variant->id, 2, 'Count');
    Inventory::adjust($branch->id, $variant->id, 2, 'Count again');

    expect(ActivityNotification::query()->where('user_id', $admin->id)->where('section', 'inventory')->count())->toBe(1)
        ->and(Inventory::query()->where('variant_id', $variant->id)->value('quantity'))->toBe(2);
});

test('supplier payment reduces the open bill', function () {
    $supplier = Supplier::factory()->create(['opening_balance' => 0]);
    $invoice = SupplierInvoice::create([
        'supplier_id' => $supplier->id,
        'invoice_no' => 'BILL-TEST-1',
        'purchase_date' => now()->toDateString(),
        'exchange_rate' => 70,
        'total_afn' => 7000,
        'paid_afn' => 0,
        'due_afn' => 7000,
    ]);

    $payment = $invoice->takePayment(2000, now()->toDateString(), 72, 'Partial', null);
    $invoice->refresh();

    expect($payment)->not->toBeNull()
        ->and((float) $invoice->paid_afn)->toBe(2000.0)
        ->and((float) $invoice->due_afn)->toBe(5000.0)
        ->and((float) $payment->exchange_rate)->toBe(72.0);
});

test('investor purchases are the rows shown on the investor account', function () {
    $investor = Investor::factory()->create();
    $product = Product::factory()->create(['name' => 'Account Rose']);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

    Product::factory()->create([
        'name' => 'Account Rose',
        'brand_id' => $product->brand_id,
        'variant_id' => $variant->id,
        'investor_id' => $investor->id,
        'exchange_rate' => 70,
        'quantity' => 2,
        'remaining_quantity' => 2,
        'line_total' => 7000,
        'purchase_price_afn' => 7000,
        'profit_per_unit' => 700,
    ]);

    $rows = Product::query()->where('investor_id', $investor->id)->whereNotNull('variant_id')->get();
    $usd = round((float) $rows->first()->line_total / (float) $rows->first()->exchange_rate, 2);

    expect($rows)->toHaveCount(1)
        ->and($usd)->toBe(100.0);
});
