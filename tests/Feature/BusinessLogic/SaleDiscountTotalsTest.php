<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Support\OriginalCurrencyMoney;
use Inertia\Testing\AssertableInertia as Assert;

test('line discount reduces sale total and receipt display amounts', function () {
    ['afn' => $afn] = saleFxEnsureCurrencies();

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $customer = Customer::factory()->create();
    $variant = ProductVariant::factory()->create();
    saleFxSeedAfnCatalog($variant, $afn, 7000);

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 5,
        'purchase_currency_id' => $afn->id,
        'purchase_unit_amount' => '3000',
        'sale_price_afn' => 7000,
        'funding_source' => 'office',
        'usd_to_afn_rate' => '75',
        'aed_to_afn_rate' => '20.56',
        'purchase_date' => now()->toDateString(),
    ], $admin, $branch->id);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'sale_date' => now()->toDateString(),
        'sale_currency_id' => $afn->id,
        'payment_method' => 'cash',
        'discount_amount' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'sale_unit_amount' => '7000',
            'discount_amount' => '200',
        ]],
        'payments' => [[
            'amount' => 6800,
            'currency_id' => $afn->id,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    expect((float) $sale->subtotal_afn)->toBe(7000.0)
        ->and((float) $sale->discount_afn)->toBe(200.0)
        ->and((float) $sale->total_afn)->toBe(6800.0)
        ->and((float) $sale->sale_amount_currency)->toBe(6800.0)
        ->and((float) $sale->new_sale_amount_afn)->toBe(6800.0)
        ->and((float) $sale->grand_total_due_afn)->toBe(6800.0);

    $item = $sale->items()->first();
    expect((float) $item->sale_price_afn)->toBe(7000.0)
        ->and((float) $item->discount_afn)->toBe(200.0)
        ->and((float) $item->line_total_afn)->toBe(6800.0);

    expect(OriginalCurrencyMoney::fromLedgerAfn((float) $sale->subtotal_afn, 'AFN', $sale))->toBe(7000.0)
        ->and(OriginalCurrencyMoney::fromLedgerAfn((float) $sale->discount_afn, 'AFN', $sale))->toBe(200.0)
        ->and(OriginalCurrencyMoney::fromLedgerAfn((float) $sale->total_afn, 'AFN', $sale))->toBe(6800.0);

    $this->actingAs($admin)
        ->get(route('sales.receipt', $sale))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sales/receipt')
            ->where('sale.display_subtotal', 7000)
            ->where('sale.display_discount', 200)
            ->where('sale.display_total', 6800)
            ->where('sale.display_new_sale_amount', 6800)
            ->where('sale.display_grand_total_due', 6800)
        );
});

test('header discount alone still reduces sale total', function () {
    ['afn' => $afn] = saleFxEnsureCurrencies();

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $customer = Customer::factory()->create();
    $variant = ProductVariant::factory()->create();
    saleFxSeedAfnCatalog($variant, $afn, 7000);

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 5,
        'purchase_currency_id' => $afn->id,
        'purchase_unit_amount' => '3000',
        'sale_price_afn' => 7000,
        'funding_source' => 'office',
        'usd_to_afn_rate' => '75',
        'aed_to_afn_rate' => '20.56',
        'purchase_date' => now()->toDateString(),
    ], $admin, $branch->id);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'sale_date' => now()->toDateString(),
        'sale_currency_id' => $afn->id,
        'payment_method' => 'cash',
        'discount_amount' => '200',
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'sale_unit_amount' => '7000',
            'discount_amount' => 0,
        ]],
        'payments' => [[
            'amount' => 6800,
            'currency_id' => $afn->id,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    expect((float) $sale->subtotal_afn)->toBe(7000.0)
        ->and((float) $sale->discount_afn)->toBe(200.0)
        ->and((float) $sale->total_afn)->toBe(6800.0)
        ->and((float) $sale->sale_amount_currency)->toBe(6800.0);
});

test('line and header discounts both reduce sale total', function () {
    ['afn' => $afn] = saleFxEnsureCurrencies();

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $customer = Customer::factory()->create();
    $variant = ProductVariant::factory()->create();
    saleFxSeedAfnCatalog($variant, $afn, 7000);

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 5,
        'purchase_currency_id' => $afn->id,
        'purchase_unit_amount' => '3000',
        'sale_price_afn' => 7000,
        'funding_source' => 'office',
        'usd_to_afn_rate' => '75',
        'aed_to_afn_rate' => '20.56',
        'purchase_date' => now()->toDateString(),
    ], $admin, $branch->id);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'sale_date' => now()->toDateString(),
        'sale_currency_id' => $afn->id,
        'payment_method' => 'cash',
        'discount_amount' => '100',
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'sale_unit_amount' => '7000',
            'discount_amount' => '200',
        ]],
        'payments' => [[
            'amount' => 6700,
            'currency_id' => $afn->id,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    expect((float) $sale->subtotal_afn)->toBe(7000.0)
        ->and((float) $sale->discount_afn)->toBe(300.0)
        ->and((float) $sale->total_afn)->toBe(6700.0)
        ->and((float) $sale->sale_amount_currency)->toBe(6700.0);
});

test('receipt includes customer previous due in grand total', function () {
    ['afn' => $afn] = saleFxEnsureCurrencies();

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);
    $customer = Customer::factory()->create(['current_due_afn' => 1500]);
    $variant = ProductVariant::factory()->create();
    saleFxSeedAfnCatalog($variant, $afn, 7000);

    app(PurchaseService::class)->receiveStock([
        'variant_id' => $variant->id,
        'quantity' => 5,
        'purchase_currency_id' => $afn->id,
        'purchase_unit_amount' => '3000',
        'sale_price_afn' => 7000,
        'funding_source' => 'office',
        'usd_to_afn_rate' => '75',
        'aed_to_afn_rate' => '20.56',
        'purchase_date' => now()->toDateString(),
    ], $admin, $branch->id);

    $sale = app(SaleService::class)->createSale([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'sale_date' => now()->toDateString(),
        'sale_currency_id' => $afn->id,
        'payment_method' => 'cash',
        'discount_amount' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'sale_unit_amount' => '7000',
            'discount_amount' => '200',
        ]],
        // Pay this sale only; previous due remains outstanding.
        'payments' => [[
            'amount' => 6800,
            'currency_id' => $afn->id,
            'payment_method' => 'cash',
        ]],
    ], $admin);

    expect((float) $sale->previous_due_afn)->toBe(1500.0)
        ->and((float) $sale->total_afn)->toBe(6800.0)
        ->and((float) $sale->new_sale_amount_afn)->toBe(6800.0)
        ->and((float) $sale->grand_total_due_afn)->toBe(8300.0)
        ->and((float) $sale->paid_afn)->toBe(6800.0)
        ->and((float) $sale->remaining_due_afn)->toBe(1500.0)
        ->and((float) $customer->fresh()->current_due_afn)->toBe(1500.0);

    $this->actingAs($admin)
        ->get(route('sales.receipt', $sale))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sales/receipt')
            ->where('sale.display_previous_due', 1500)
            ->where('sale.display_new_sale_amount', 6800)
            ->where('sale.display_grand_total_due', 8300)
            ->where('sale.display_paid', 6800)
            ->where('sale.display_remaining', 1500)
        );
});
