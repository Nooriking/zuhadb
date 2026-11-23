<?php

use App\Ai\Tools\ListLowStockTool;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tool = app(ListLowStockTool::class);
});

function makeInventoryRow(Branch $branch, string $name, string $sku, int $available, bool $trashProduct = false): Inventory
{
    $brand = Brand::factory()->create();
    $product = Product::factory()->create([
        'brand_id' => $brand->id,
        'name' => $name,
        'status' => 'active',
        'trash' => $trashProduct,
    ]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => $name.' variant',
        'sku' => $sku,
        'trash' => $trashProduct,
    ]);

    return Inventory::factory()->create([
        'branch_id' => $branch->id,
        'variant_id' => $variant->id,
        'quantity' => $available,
        'reserved_quantity' => 0,
        'available_quantity' => $available,
        'average_cost_afn' => 9999,
        'trash' => false,
    ]);
}

test('list low stock uses Inventory LOW_STOCK_THRESHOLD and excludes above-threshold and out of stock', function () {
    $branch = Branch::factory()->create(['name' => 'Main']);
    makeInventoryRow($branch, 'Low One', 'LOW-1', 1);
    makeInventoryRow($branch, 'Low Two', 'LOW-2', Inventory::LOW_STOCK_THRESHOLD);
    makeInventoryRow($branch, 'Healthy', 'OK-1', Inventory::LOW_STOCK_THRESHOLD + 1);
    makeInventoryRow($branch, 'Empty', 'OUT-1', 0);

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $result = $this->tool->execute($admin, ['branch_id' => $branch->id, 'limit' => 20]);

    expect($result['match_type'])->toBe('low_stock')
        ->and($result['low_stock_threshold'])->toBe(Inventory::LOW_STOCK_THRESHOLD)
        ->and($result['threshold_source'])->toBe('App\\Models\\Inventory::LOW_STOCK_THRESHOLD')
        ->and($result['count'])->toBe(2);

    $skus = collect($result['items'])->pluck('sku')->all();
    expect($skus)->toContain('LOW-1')
        ->and($skus)->toContain('LOW-2')
        ->and($skus)->not->toContain('OK-1')
        ->and($skus)->not->toContain('OUT-1');

    foreach ($result['items'] as $item) {
        expect($item['stock_status'])->toBe('low_stock')
            ->and($item['reorder_threshold'])->toBe(Inventory::LOW_STOCK_THRESHOLD)
            ->and($item['stock_quantity'])->toBeLessThanOrEqual(Inventory::LOW_STOCK_THRESHOLD)
            ->and($item['stock_quantity'])->toBeGreaterThan(0);
    }

    $encoded = json_encode($result);
    expect($encoded)->not->toContain('average_cost')
        ->and($encoded)->not->toContain('9999')
        ->and($encoded)->not->toContain('profit')
        ->and($encoded)->not->toContain('unit_cost');
});

test('list low stock respects branch scope and rejects unauthorized branch', function () {
    $branchA = Branch::factory()->create(['name' => 'A']);
    $branchB = Branch::factory()->create(['name' => 'B']);
    makeInventoryRow($branchA, 'Scoped Low', 'SC-A', 1);
    makeInventoryRow($branchB, 'Other Low', 'SC-B', 1);

    $manager = User::factory()->create([
        'role' => User::ROLE_BRANCH_MANAGER,
        'branch_id' => $branchA->id,
    ]);

    $own = $this->tool->execute($manager, []);
    expect($own['count'])->toBe(1)
        ->and($own['items'][0]['sku'])->toBe('SC-A')
        ->and($own['branch_ids'])->toBe([$branchA->id]);

    $denied = $this->tool->execute($manager, ['branch_id' => $branchB->id]);
    expect($denied['error'])->toBe('unauthorized_branch')
        ->and($denied['items'])->toBe([]);
});

test('list low stock enforces default and maximum limits', function () {
    $branch = Branch::factory()->create();
    for ($i = 1; $i <= 55; $i++) {
        makeInventoryRow($branch, "Low {$i}", "LIM-{$i}", 1);
    }

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $default = $this->tool->execute($admin, ['branch_id' => $branch->id]);
    expect($default['limit'])->toBe(ListLowStockTool::DEFAULT_LIMIT)
        ->and($default['count'])->toBe(ListLowStockTool::DEFAULT_LIMIT);

    $clamped = $this->tool->execute($admin, ['branch_id' => $branch->id, 'limit' => 999]);
    expect($clamped['limit'])->toBe(ListLowStockTool::MAX_LIMIT)
        ->and($clamped['count'])->toBe(ListLowStockTool::MAX_LIMIT);

    $small = $this->tool->execute($admin, ['branch_id' => $branch->id, 'limit' => 3]);
    expect($small['limit'])->toBe(3)->and($small['count'])->toBe(3);
});

test('list low stock returns empty results safely', function () {
    $branch = Branch::factory()->create();
    makeInventoryRow($branch, 'Plenty', 'PLENTY', 50);

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $result = $this->tool->execute($admin, ['branch_id' => $branch->id]);

    expect($result['count'])->toBe(0)
        ->and($result['items'])->toBe([])
        ->and($result['low_stock_threshold'])->toBe(Inventory::LOW_STOCK_THRESHOLD);
});

test('list low stock excludes trashed products', function () {
    $branch = Branch::factory()->create();
    makeInventoryRow($branch, 'Live Low', 'LIVE-L', 1, false);
    makeInventoryRow($branch, 'Trash Low', 'TRASH-L', 1, true);

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $result = $this->tool->execute($admin, ['branch_id' => $branch->id]);

    expect($result['count'])->toBe(1)
        ->and($result['items'][0]['sku'])->toBe('LIVE-L');
});

test('list low stock authorize requires inventory view', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $investor = User::factory()->create(['role' => User::ROLE_INVESTOR]);
    $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

    expect($this->tool->authorize($admin))->toBeTrue()
        ->and($this->tool->authorize($cashier))->toBeTrue()
        ->and($this->tool->authorize($investor))->toBeFalse();
});
