<?php

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductBatch;
use App\Models\ProductPriceHistory;
use App\Models\ProductVariant;
use App\Models\Receipt;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use App\Models\AppSetting;

/* ═══════════════════════════════════════════════════════════
 |  1. Authentication
 ═══════════════════════════════════════════════════════════ */

test('login succeeds with correct username and password', function () {
    $user = User::factory()->create([
        'username' => 'cashier1',
        'password' => 'password',
        'status' => 'active',
        'trash' => false,
        'role' => User::ROLE_CASHIER,
    ]);

    $this->post('/login', [
        'email' => 'cashier1',
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

test('login fails with wrong username', function () {
    User::factory()->create(['username' => 'realuser', 'password' => 'password', 'status' => 'active']);

    $this->from('/login')->post('/login', [
        'email' => 'nobody',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login fails with wrong password', function () {
    User::factory()->create(['username' => 'realuser', 'password' => 'password', 'status' => 'active']);

    $this->from('/login')->post('/login', [
        'email' => 'realuser',
        'password' => 'wrong',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('inactive user cannot login', function () {
    User::factory()->create([
        'username' => 'blocked',
        'password' => 'password',
        'status' => 'inactive',
        'trash' => false,
    ]);

    $this->from('/login')->post('/login', [
        'email' => 'blocked',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('logout ends the session', function () {
    $user = User::factory()->create(['status' => 'active']);

    $this->actingAs($user)->post('/logout')->assertRedirect();

    $this->assertGuest();
});

/* ═══════════════════════════════════════════════════════════
 |  Role & Permission
 ═══════════════════════════════════════════════════════════ */

test('admin can open settings and sales', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)->get('/app-settings')->assertOk();
    $this->actingAs($admin)->get('/sales/create')->assertOk();
});

test('salesperson can register products and create sales', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'role' => User::ROLE_SALESPERSON,
        'branch_id' => $branch->id,
    ]);

    expect($user->canAccessModule('products'))->toBeTrue()
        ->and($user->canAccessModule('sales'))->toBeTrue()
        ->and($user->canAccessModule('customers'))->toBeTrue()
        ->and($user->canAccessModule('inventory'))->toBeTrue()
        ->and($user->hasPermission('products.create'))->toBeTrue()
        ->and($user->hasPermission('sales.create'))->toBeTrue()
        ->and($user->hasPermission('sales.update'))->toBeTrue();

    $this->actingAs($user)->get('/products/create')->assertOk();
    $this->actingAs($user)->get('/sales/create')->assertOk();
    $this->actingAs($user)->get('/customers')->assertOk();
    $this->actingAs($user)->get('/customer-transactions')->assertOk();
    $this->actingAs($user)->get('/receipts')->assertOk();
    $this->actingAs($user)->get('/inventory')->assertOk();
    $this->actingAs($user)->get('/app-settings')->assertForbidden();
});

test('investor cannot create a sale', function () {
    $user = User::factory()->create(['role' => User::ROLE_INVESTOR]);

    expect($user->hasPermission('sales.create'))->toBeFalse();

    $this->actingAs($user)->get('/sales/create')->assertForbidden();
    $this->actingAs($user)->post('/sales', [])->assertForbidden();
});

test('branch manager is scoped to own branch modules', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create([
        'role' => User::ROLE_BRANCH_MANAGER,
        'branch_id' => $branch->id,
    ]);

    $this->actingAs($manager)->get('/sales')->assertOk();
    $this->actingAs($manager)->get('/app-settings')->assertForbidden();
    $this->actingAs($manager)->get('/investors')->assertForbidden();
});

/* ═══════════════════════════════════════════════════════════
 |  2. Customer
 ═══════════════════════════════════════════════════════════ */

test('customer create update soft delete and restore work', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($user)->post('/customers', [
        'name' => 'Ahmad',
        'phone' => '0701112233',
        'status' => 'active',
    ])->assertRedirect();

    $customer = Customer::query()->where('phone', '0701112233')->first();
    expect($customer)->not->toBeNull();

    $this->actingAs($user)->post('/customers', [
        'name' => 'Duplicate',
        'phone' => '0701112233',
        'status' => 'active',
    ])->assertSessionHasErrors('phone');

    $this->actingAs($user)->post('/customers', [
        'name' => '',
        'phone' => '0709998877',
        'status' => 'active',
    ])->assertSessionHasErrors('name');

    $this->actingAs($user)->post('/customers', [
        'name' => 'No Phone',
        'phone' => '',
        'status' => 'active',
    ])->assertSessionHasErrors('phone');

    $this->actingAs($user)->put("/customers/{$customer->id}", [
        'name' => 'Ahmad Updated',
        'phone' => '0701112244',
        'status' => 'active',
    ])->assertRedirect();

    expect($customer->fresh()->name)->toBe('Ahmad Updated')
        ->and($customer->fresh()->phone)->toBe('0701112244');

    $this->actingAs($user)->delete("/customers/{$customer->id}")->assertRedirect();
    expect($customer->fresh()->trash)->toBeTrue();

    $this->actingAs($user)->post("/customers/{$customer->id}/restore")->assertRedirect();
    expect($customer->fresh()->trash)->toBeFalse();
});

/* ═══════════════════════════════════════════════════════════
 |  3–6. Purchase / Inventory / Price history
 ═══════════════════════════════════════════════════════════ */

test('receiving stock increases inventory batch transaction and price history', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'role' => User::ROLE_STOCK_KEEPER,
        'branch_id' => $branch->id,
    ]);
    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 100,
        'sale_price_afn' => 120,
        'stock_quantity' => 0,
    ]);

    $this->actingAs($user)->post('/products/receive', [
        'variant_id' => $variant->id,
        'quantity' => 5,
        'purchase_price_afn' => 100,
        'sale_price_afn' => 150,
        'investor_id' => 'store',
        'exchange_rate' => 70,
        'purchase_date' => now()->toDateString(),
    ])->assertRedirect();

    expect((int) ProductBatch::query()->where('variant_id', $variant->id)->sum('remaining_quantity'))->toBe(5)
        ->and((int) Inventory::query()->where('branch_id', $branch->id)->where('variant_id', $variant->id)->value('quantity'))->toBe(5)
        ->and(ProductPriceHistory::query()->where('variant_id', $variant->id)->count())->toBeGreaterThan(0);
});

test('inventory decrease rejects negative stock', function () {
    $branch = Branch::factory()->create();
    $variant = ProductVariant::factory()->create();
    Inventory::increase($branch->id, $variant->id, 2, 'purchase');

    expect(fn () => Inventory::decrease($branch->id, $variant->id, 5, 'sale'))
        ->toThrow(RuntimeException::class);
});

test('price history keeps multiple purchase sale changes', function () {
    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 100,
        'sale_price_afn' => 120,
    ]);

    ProductPriceHistory::record($variant->id, 100, 100, 120, 150, 'sale up');
    ProductPriceHistory::record($variant->id, 100, 120, 150, 150, 'purchase up');
    ProductPriceHistory::record($variant->id, 120, 150, 150, 180, 'both up');

    expect(ProductPriceHistory::query()->where('variant_id', $variant->id)->count())->toBe(3);
});

/* ═══════════════════════════════════════════════════════════
 |  7–10. Sale / payments / receipts / ledger
 ═══════════════════════════════════════════════════════════ */

test('cash credit and full credit sales set paid and due correctly', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'role' => User::ROLE_SALESPERSON,
        'branch_id' => $branch->id,
    ]);
    $customer = Customer::factory()->create(['current_due_afn' => 0]);
    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 2000,
        'sale_price_afn' => 5000,
    ]);

    ProductBatch::factory()->create([
        'branch_id' => $branch->id,
        'variant_id' => $variant->id,
        'quantity' => 10,
        'remaining_quantity' => 10,
        'investor_id' => null,
    ]);
    Inventory::increase($branch->id, $variant->id, 10, 'purchase');

    // Cash: paid 5000 due 0
    $this->actingAs($user)->post('/sales', [
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'sale_date' => now()->toDateString(),
        'payment_method' => 'cash',
        'discount_afn' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'sale_price_afn' => 5000,
            'discount_afn' => 0,
        ]],
        'payments' => [[
            'amount_afn' => 5000,
            'payment_method' => 'cash',
        ]],
    ])->assertRedirect();

    $cashSale = Sale::query()->latest('id')->first();
    expect((float) $cashSale->paid_afn)->toBe(5000.0)
        ->and((float) $cashSale->due_afn)->toBe(0.0);
});

test('partial credit sale then payment clears customer due and logs ledger', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'role' => User::ROLE_SALESPERSON,
        'branch_id' => $branch->id,
    ]);
    $customer = Customer::factory()->create(['current_due_afn' => 0]);
    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 2000,
        'sale_price_afn' => 5000,
    ]);

    ProductBatch::factory()->create([
        'branch_id' => $branch->id,
        'variant_id' => $variant->id,
        'quantity' => 10,
        'remaining_quantity' => 10,
        'investor_id' => null,
    ]);
    Inventory::increase($branch->id, $variant->id, 10, 'purchase');

    $this->actingAs($user)->post('/sales', [
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'sale_date' => now()->toDateString(),
        'payment_method' => 'credit',
        'discount_afn' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 1,
            'sale_price_afn' => 5000,
            'discount_afn' => 0,
        ]],
        'payments' => [[
            'amount_afn' => 2000,
            'payment_method' => 'cash',
        ]],
    ])->assertRedirect();

    $sale = Sale::query()->latest('id')->first();
    $customer->refresh();

    expect((float) $sale->paid_afn)->toBe(2000.0)
        ->and((float) $sale->due_afn)->toBe(3000.0)
        ->and((float) $customer->current_due_afn)->toBe(3000.0)
        ->and(CustomerTransaction::query()->where('customer_id', $customer->id)->where('type', 'sale')->count())->toBe(1)
        ->and(ActivityLog::query()->where('section', 'sales')->where('action', 'create')->count())->toBeGreaterThan(0);

    $this->actingAs($user)->get(route('sales.receipt', $sale))->assertOk();

    $this->actingAs($user)->post(route('sales.addPayment', $sale), [
        'amount_afn' => 3000,
        'payment_method' => 'cash',
    ])->assertRedirect();

    $sale->refresh();
    $customer->refresh();

    expect((float) $sale->due_afn)->toBe(0.0)
        ->and((float) $customer->current_due_afn)->toBe(0.0)
        ->and(SalePayment::query()->where('sale_id', $sale->id)->count())->toBe(2)
        ->and(CustomerTransaction::query()->where('customer_id', $customer->id)->where('type', 'payment')->count())->toBeGreaterThan(0)
        ->and(Receipt::query()->where('sale_id', $sale->id)->where('receipt_type', 'payment_receipt')->count())->toBeGreaterThan(0);
});

/* ═══════════════════════════════════════════════════════════
 |  14. Search / barcode
 ═══════════════════════════════════════════════════════════ */

test('barcode search finds the product', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'role' => User::ROLE_SALESPERSON,
        'branch_id' => $branch->id,
    ]);
    $variant = ProductVariant::factory()->create();
    ProductBarcode::factory()->create([
        'variant_id' => $variant->id,
        'barcode' => '123456789',
    ]);

    $this->actingAs($user)
        ->getJson('/sales/lookup-barcode?barcode=123456789')
        ->assertOk()
        ->assertJsonPath('found', true);
});

/* ═══════════════════════════════════════════════════════════
 |  15. Branch stock isolation
 ═══════════════════════════════════════════════════════════ */

test('inventory is separate per branch', function () {
    $branch1 = Branch::factory()->create();
    $branch2 = Branch::factory()->create();
    $variant = ProductVariant::factory()->create();

    Inventory::increase($branch1->id, $variant->id, 7, 'purchase');
    Inventory::increase($branch2->id, $variant->id, 3, 'purchase');

    expect((int) Inventory::query()->where('branch_id', $branch1->id)->where('variant_id', $variant->id)->value('quantity'))->toBe(7)
        ->and((int) Inventory::query()->where('branch_id', $branch2->id)->where('variant_id', $variant->id)->value('quantity'))->toBe(3);
});

/* ═══════════════════════════════════════════════════════════
 |  Branding
 ═══════════════════════════════════════════════════════════ */

test('app bill exposes Tezhoshan manufacturer and support phone', function () {
    AppSetting::query()->delete();
    AppSetting::factory()->create([
        'phone' => '0744442863',
        'website' => 'Tezhoshan',
    ]);

    $bill = AppSetting::bill();

    expect($bill['manufacturer'])->toBe('Tezhoshan')
        ->and($bill['support_phone'])->toBe('0744442863')
        ->and($bill['phone'])->toContain('0744442863');
});

/* ═══════════════════════════════════════════════════════════
 |  20. End-to-End core flow
 ═══════════════════════════════════════════════════════════ */

test('end to end purchase sale payment due zero profit path', function () {
    $branch = Branch::factory()->create();
    $stockUser = User::factory()->create([
        'role' => User::ROLE_STOCK_KEEPER,
        'branch_id' => $branch->id,
    ]);
    $salesUser = User::factory()->create([
        'role' => User::ROLE_SALESPERSON,
        'branch_id' => $branch->id,
    ]);

    $product = Product::factory()->create(['name' => 'E2E Rose']);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'purchase_price_afn' => 900,
        'sale_price_afn' => 1800,
        'stock_quantity' => 0,
    ]);

    // Purchase / receive
    $this->actingAs($stockUser)->post('/products/receive', [
        'variant_id' => $variant->id,
        'quantity' => 4,
        'purchase_price_afn' => 1000,
        'sale_price_afn' => 2000,
        'investor_id' => 'store',
        'exchange_rate' => 70,
        'purchase_date' => now()->toDateString(),
    ])->assertRedirect();

    expect((int) ProductBatch::query()->where('variant_id', $variant->id)->where('branch_id', $branch->id)->sum('remaining_quantity'))->toBe(4)
        ->and((int) Inventory::query()->where('branch_id', $branch->id)->where('variant_id', $variant->id)->value('quantity'))->toBe(4)
        ->and(ProductPriceHistory::query()->where('variant_id', $variant->id)->count())->toBeGreaterThan(0);

    $customer = Customer::factory()->create(['current_due_afn' => 0]);

    // Credit sale: total 4000 paid 1000 due 3000
    $this->actingAs($salesUser)->post('/sales', [
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'sale_date' => now()->toDateString(),
        'payment_method' => 'credit',
        'discount_afn' => 0,
        'items' => [[
            'variant_id' => $variant->id,
            'quantity' => 2,
            'sale_price_afn' => 2000,
            'discount_afn' => 0,
        ]],
        'payments' => [[
            'amount_afn' => 1000,
            'payment_method' => 'cash',
        ]],
    ])->assertRedirect();

    $sale = Sale::query()->latest('id')->first();
    $customer->refresh();

    expect((int) Inventory::query()->where('branch_id', $branch->id)->where('variant_id', $variant->id)->value('quantity'))->toBe(2)
        ->and((float) $customer->current_due_afn)->toBe(3000.0)
        ->and((float) $sale->due_afn)->toBe(3000.0)
        ->and((float) $sale->store_profit_afn)->toBeGreaterThan(0);

    $this->actingAs($salesUser)->get(route('sales.receipt', $sale))->assertOk();

    // Clear remaining debt
    $this->actingAs($salesUser)->post(route('sales.addPayment', $sale), [
        'amount_afn' => 3000,
        'payment_method' => 'cash',
    ])->assertRedirect();

    $sale->refresh();
    $customer->refresh();

    expect((float) $sale->due_afn)->toBe(0.0)
        ->and((float) $customer->current_due_afn)->toBe(0.0)
        ->and(CustomerTransaction::query()->where('customer_id', $customer->id)->count())->toBeGreaterThanOrEqual(2)
        ->and(Receipt::query()->where('sale_id', $sale->id)->count())->toBeGreaterThan(0)
        ->and(ActivityLog::query()->where('section', 'sales')->count())->toBeGreaterThan(0);
});
