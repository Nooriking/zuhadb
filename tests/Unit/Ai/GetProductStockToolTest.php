<?php

use App\Ai\Tools\GetProductStockTool;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tool = app(GetProductStockTool::class);
});

function makeStockProduct(string $name, string $sku, string $barcode, Brand $brand): array
{
    $product = Product::factory()->create([
        'brand_id' => $brand->id,
        'name' => $name,
        'status' => 'active',
        'trash' => false,
    ]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => $name.' 100ml',
        'sku' => $sku,
        'volume_ml' => 100,
        'concentration' => 'edp',
        'trash' => false,
    ]);
    ProductBarcode::factory()->create([
        'variant_id' => $variant->id,
        'barcode' => $barcode,
        'is_primary' => true,
        'status' => 'active',
    ]);

    return compact('product', 'variant');
}

test('get product stock finds by name sku and barcode using inventories available quantity', function () {
    $brand = Brand::factory()->create(['name' => 'Dior']);
    $branchA = Branch::factory()->create(['name' => 'Kabul Main', 'code' => 'KB1']);
    $branchB = Branch::factory()->create(['name' => 'Herat', 'code' => 'HR1']);
    ['product' => $product, 'variant' => $variant] = makeStockProduct('Sauvage', 'DS-001', '8901111222333', $brand);

    Inventory::factory()->create([
        'branch_id' => $branchA->id,
        'variant_id' => $variant->id,
        'quantity' => 12,
        'reserved_quantity' => 2,
        'available_quantity' => 10,
        'average_cost_afn' => 4500,
        'trash' => false,
    ]);
    Inventory::factory()->create([
        'branch_id' => $branchB->id,
        'variant_id' => $variant->id,
        'quantity' => 3,
        'reserved_quantity' => 0,
        'available_quantity' => 3,
        'average_cost_afn' => 4500,
        'trash' => false,
    ]);

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => null]);

    $byName = $this->tool->execute($admin, ['query' => 'Sauvage']);
    expect($byName['match_type'])->toBe('product')
        ->and($byName['count'])->toBe(1)
        ->and($byName['items'][0]['sku'])->toBe('DS-001')
        ->and($byName['items'][0]['total_stock_quantity'])->toBe(13)
        ->and($byName['low_stock_threshold'])->toBe(Inventory::LOW_STOCK_THRESHOLD);

    $branchIds = collect($byName['items'][0]['branches'])->pluck('branch_id')->all();
    expect($branchIds)->toContain($branchA->id)->toContain($branchB->id);

    $kabul = collect($byName['items'][0]['branches'])->firstWhere('branch_id', $branchA->id);
    expect($kabul['stock_quantity'])->toBe(10)
        ->and($kabul['stock_status'])->toBe('in_stock');

    $bySku = $this->tool->execute($admin, ['query' => 'DS-001']);
    expect($bySku['match_type'])->toBe('exact')
        ->and($bySku['items'][0]['product_id'])->toBe($product->id);

    $byBarcode = $this->tool->execute($admin, ['query' => '8901111222333']);
    expect($byBarcode['match_type'])->toBe('exact')
        ->and($byBarcode['items'][0]['barcode'])->toBe('8901111222333');

    $encoded = json_encode($byName);
    expect($encoded)->not->toContain('average_cost')
        ->and($encoded)->not->toContain('4500')
        ->and($encoded)->not->toContain('profit')
        ->and($encoded)->not->toContain('unit_cost')
        ->and($encoded)->not->toContain('investor');
});

test('get product stock returns candidates when multiple products match', function () {
    Branch::factory()->create();
    $brand = Brand::factory()->create(['name' => 'SharedBrand']);
    makeStockProduct('Bleu One', 'BLU-1', '1000000000001', $brand);
    makeStockProduct('Bleu Two', 'BLU-2', '1000000000002', $brand);

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $result = $this->tool->execute($admin, ['query' => 'Bleu']);

    expect($result['match_type'])->toBe('ambiguous')
        ->and($result['items'])->toBe([])
        ->and(count($result['candidates']))->toBe(2);
});

test('get product stock returns not found for unknown query', function () {
    Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $result = $this->tool->execute($admin, ['query' => 'CompletelyUnknownPerfumeXYZ']);

    expect($result['match_type'])->toBe('not_found')
        ->and($result['items'])->toBe([]);
});

test('get product stock respects branch filter and denies unauthorized branch', function () {
    $brand = Brand::factory()->create(['name' => 'Chanel']);
    $branchA = Branch::factory()->create(['name' => 'Branch A']);
    $branchB = Branch::factory()->create(['name' => 'Branch B']);
    ['variant' => $variant] = makeStockProduct('Chance', 'CH-1', '2000000000001', $brand);

    Inventory::factory()->create([
        'branch_id' => $branchA->id,
        'variant_id' => $variant->id,
        'available_quantity' => 8,
        'quantity' => 8,
        'trash' => false,
    ]);
    Inventory::factory()->create([
        'branch_id' => $branchB->id,
        'variant_id' => $variant->id,
        'available_quantity' => 4,
        'quantity' => 4,
        'trash' => false,
    ]);

    $manager = User::factory()->create([
        'role' => User::ROLE_BRANCH_MANAGER,
        'branch_id' => $branchA->id,
    ]);

    $own = $this->tool->execute($manager, ['query' => 'CH-1', 'branch_id' => $branchA->id]);
    expect($own['match_type'])->toBe('exact')
        ->and($own['items'][0]['branches'])->toHaveCount(1)
        ->and($own['items'][0]['branches'][0]['stock_quantity'])->toBe(8);

    $denied = $this->tool->execute($manager, ['query' => 'CH-1', 'branch_id' => $branchB->id]);
    expect($denied['error'])->toBe('unauthorized_branch')
        ->and($denied['items'])->toBe([]);

    $scopedDefault = $this->tool->execute($manager, ['query' => 'CH-1']);
    expect($scopedDefault['items'][0]['branches'])->toHaveCount(1)
        ->and($scopedDefault['items'][0]['branches'][0]['branch_id'])->toBe($branchA->id);
});

test('get product stock reports missing inventory record as zero with flag', function () {
    $brand = Brand::factory()->create(['name' => 'Tom Ford']);
    $branch = Branch::factory()->create();
    makeStockProduct('Oud Wood', 'TF-OW', '3000000000001', $brand);

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $result = $this->tool->execute($admin, [
        'query' => 'TF-OW',
        'branch_id' => $branch->id,
    ]);

    expect($result['match_type'])->toBe('exact')
        ->and($result['items'][0]['branches'][0]['stock_quantity'])->toBe(0)
        ->and($result['items'][0]['branches'][0]['inventory_record_exists'])->toBeFalse()
        ->and($result['items'][0]['branches'][0]['stock_status'])->toBe('out_of_stock');
});

test('get product stock excludes trashed products and marks low stock status', function () {
    $brand = Brand::factory()->create(['name' => 'Lattafa']);
    $branch = Branch::factory()->create();

    ['variant' => $liveVariant] = makeStockProduct('Asad Live', 'ASAD-L', '4000000000001', $brand);
    Inventory::factory()->create([
        'branch_id' => $branch->id,
        'variant_id' => $liveVariant->id,
        'available_quantity' => 1,
        'quantity' => 1,
        'trash' => false,
    ]);

    $trashed = Product::factory()->create([
        'brand_id' => $brand->id,
        'name' => 'Asad Trashed',
        'trash' => true,
    ]);
    $trashedVariant = ProductVariant::factory()->create([
        'product_id' => $trashed->id,
        'sku' => 'ASAD-T',
        'trash' => true,
    ]);
    Inventory::factory()->create([
        'branch_id' => $branch->id,
        'variant_id' => $trashedVariant->id,
        'available_quantity' => 1,
        'trash' => false,
    ]);

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $result = $this->tool->execute($admin, [
        'query' => 'Asad',
        'branch_id' => $branch->id,
    ]);

    expect($result['match_type'])->toBe('product')
        ->and($result['count'])->toBe(1)
        ->and($result['items'][0]['sku'])->toBe('ASAD-L')
        ->and($result['items'][0]['branches'][0]['stock_status'])->toBe('low_stock');
});

test('get product stock authorize requires inventory view and blocks investors', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
    $investor = User::factory()->create(['role' => User::ROLE_INVESTOR]);
    $accountant = User::factory()->create(['role' => User::ROLE_ACCOUNTANT]);

    expect($this->tool->authorize($admin))->toBeTrue()
        ->and($this->tool->authorize($cashier))->toBeTrue()
        ->and($this->tool->authorize($investor))->toBeFalse()
        ->and($this->tool->authorize($accountant))->toBeFalse();
});

test('get product stock includes inactive products following catalog visibility', function () {
    $brand = Brand::factory()->create(['name' => 'InactiveBrand']);
    $branch = Branch::factory()->create();
    $product = Product::factory()->create([
        'brand_id' => $brand->id,
        'name' => 'Inactive Scent',
        'status' => 'inactive',
        'trash' => false,
    ]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'INACT-1',
        'trash' => false,
    ]);
    Inventory::factory()->create([
        'branch_id' => $branch->id,
        'variant_id' => $variant->id,
        'available_quantity' => 5,
        'quantity' => 5,
        'trash' => false,
    ]);

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $result = $this->tool->execute($admin, [
        'query' => 'INACT-1',
        'branch_id' => $branch->id,
    ]);

    expect($result['match_type'])->toBe('exact')
        ->and($result['items'][0]['product_status'])->toBe('inactive')
        ->and($result['items'][0]['branches'][0]['stock_quantity'])->toBe(5);
});
