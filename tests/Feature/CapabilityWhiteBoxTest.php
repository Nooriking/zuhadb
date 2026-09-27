<?php

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Investor;
use App\Models\ProductBarcode;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\User;

test('barcode lookup reads the product row from the database', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'role' => User::ROLE_STOCK_KEEPER,
        'branch_id' => $branch->id,
    ]);
    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 1800,
        'sale_price_afn' => 2500,
        'stock_quantity' => 7,
    ]);
    ProductBarcode::factory()->create([
        'variant_id' => $variant->id,
        'barcode' => 'SCAN-DB-1001',
    ]);

    $found = $this->actingAs($user)->getJson('/products/receive/lookup-barcode?barcode=SCAN-DB-1001');
    $missing = $this->actingAs($user)->getJson('/products/receive/lookup-barcode?barcode=DOES-NOT-EXIST');

    $found->assertOk()
        ->assertJsonPath('found', true)
        ->assertJsonPath('variant.id', $variant->id)
        ->assertJsonPath('variant.stock_quantity', 7);

    $missing->assertOk()->assertJsonPath('found', false);
});

test('add stock rejects a sale price below the purchase price', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'role' => User::ROLE_STOCK_KEEPER,
        'branch_id' => $branch->id,
    ]);
    $variant = ProductVariant::factory()->create();

    $this->actingAs($user)->post('/products/receive', [
        'variant_id' => $variant->id,
        'quantity' => 2,
        'purchase_price_afn' => 3000,
        'sale_price_afn' => 2000,
        'funding_source' => 'office',
        'investor_id' => 'store',
        'purchase_date' => now()->toDateString(),
    ])->assertSessionHasErrors('sale_price_afn');

    expect(ProductBatch::query()->where('variant_id', $variant->id)->count())->toBe(0);
});

test('add stock rejects investor capital when the dollar rate is missing', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'branch_id' => $branch->id,
    ]);
    $variant = ProductVariant::factory()->create();
    $investor = Investor::factory()->create();
    $investment = \App\Models\Investment::factory()->create([
        'investor_id' => $investor->id,
        'available_amount_afn' => 5000,
        'capital_amount_afn' => 5000,
    ]);

    $this->actingAs($user)->post('/products/receive', [
        'variant_id' => $variant->id,
        'quantity' => 1,
        'purchase_price_afn' => 1000,
        'sale_price_afn' => 1500,
        'funding_source' => 'investor',
        'investor_id' => (string) $investor->id,
        'investment_id' => $investment->id,
        'profit_per_unit' => 70,
        'exchange_rate' => 0,
        'purchase_date' => now()->toDateString(),
    ])->assertSessionHasErrors('exchange_rate');
});

test('add stock for the shop increases inventory and stores the dollar profit as zero', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'role' => User::ROLE_STOCK_KEEPER,
        'branch_id' => $branch->id,
    ]);
    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 1000,
        'sale_price_afn' => 1400,
    ]);

    $this->actingAs($user)->post('/products/receive', [
        'variant_id' => $variant->id,
        'quantity' => 3,
        'purchase_price_afn' => 1100,
        'sale_price_afn' => 1600,
        'funding_source' => 'office',
        'investor_id' => 'store',
        'exchange_rate' => 70,
        'purchase_date' => now()->toDateString(),
    ])->assertRedirect();

    $batch = ProductBatch::query()->where('variant_id', $variant->id)->first();
    $stock = (int) Inventory::query()->where('branch_id', $branch->id)->where('variant_id', $variant->id)->value('quantity');

    expect($batch)->not->toBeNull()
        ->and((int) $batch->quantity)->toBe(3)
        ->and((float) $batch->profit_per_unit_afn)->toBe(0.0)
        ->and($stock)->toBe(3);

    $variant->refresh();
    expect((float) $variant->sale_price_afn)->toBe(1600.0)
        ->and((float) $variant->purchase_price_afn)->toBe(1100.0);
});

test('add stock with investor capital allocates investment and stores profit per unit', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'branch_id' => $branch->id,
    ]);
    $variant = ProductVariant::factory()->create([
        'purchase_price_afn' => 1000,
        'sale_price_afn' => 1400,
    ]);
    $investor = Investor::factory()->create();
    $investment = \App\Models\Investment::factory()->create([
        'investor_id' => $investor->id,
        'capital_amount_afn' => 1000,
        'available_amount_afn' => 1000,
        'allocated_amount_afn' => 0,
        'status' => 'active',
    ]);

    $this->actingAs($user)->post('/products/receive', [
        'variant_id' => $variant->id,
        'quantity' => 2,
        'purchase_price_afn' => 700,
        'sale_price_afn' => 1200,
        'funding_source' => 'investor',
        'investor_id' => (string) $investor->id,
        'investment_id' => $investment->id,
        'profit_per_unit' => 70,
        'exchange_rate' => 70,
        'purchase_date' => now()->toDateString(),
    ])->assertRedirect();

    $investment->refresh();
    $batch = ProductBatch::query()->where('variant_id', $variant->id)->first();
    $productAllocation = \App\Models\InvestmentProductAllocation::query()->where('batch_id', $batch->id)->first();
    $purchaseAllocation = \App\Models\InvestmentPurchaseAllocation::query()->where('investment_id', $investment->id)->first();

    expect((float) $investment->available_amount_afn)->toBe(980.0)
        ->and((float) $investment->allocated_amount_afn)->toBe(20.0)
        ->and($batch->investor_id)->toBe($investor->id)
        ->and((float) $batch->profit_per_unit_afn)->toBe(1.0)
        ->and($productAllocation)->not->toBeNull()
        ->and((int) $productAllocation->funded_quantity)->toBe(2)
        ->and((float) $productAllocation->profit_per_unit_afn)->toBe(1.0)
        ->and($purchaseAllocation)->not->toBeNull()
        ->and((float) $purchaseAllocation->allocated_amount_afn)->toBe(20.0);
});

test('a branch manager is locked to one branch and an admin is not', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create([
        'role' => User::ROLE_BRANCH_MANAGER,
        'branch_id' => $branch->id,
    ]);
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'branch_id' => $branch->id,
    ]);

    expect($manager->isBranchScoped())->toBeTrue()
        ->and($admin->isBranchScoped())->toBeFalse()
        ->and($manager->canAccessModule('users'))->toBeTrue()
        ->and($manager->hasPermission('users.create'))->toBeTrue()
        ->and($manager->canAccessModule('investors'))->toBeFalse();
});

test('a cashier cannot look up a receive barcode', function () {
    $user = User::factory()->create(['role' => User::ROLE_CASHIER]);

    $this->actingAs($user)
        ->getJson('/products/receive/lookup-barcode?barcode=ANY')
        ->assertForbidden();
});
