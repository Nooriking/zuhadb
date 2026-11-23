<?php

use App\Ai\Tools\SearchProductsTool;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tool = app(SearchProductsTool::class);
});

test('search products finds by name brand sku and barcode without stock or cost fields', function () {
    $brand = Brand::factory()->create(['name' => 'Dior']);
    $product = Product::factory()->create([
        'brand_id' => $brand->id,
        'name' => 'Sauvage Elixir',
        'status' => 'active',
        'trash' => false,
    ]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'DIOR-SAV-100',
        'volume_ml' => 100,
        'concentration' => 'edp',
        'trash' => false,
    ]);
    ProductBarcode::factory()->create([
        'variant_id' => $variant->id,
        'barcode' => '8901234567890',
        'is_primary' => true,
        'status' => 'active',
        'current_stock' => 99,
    ]);

    Product::factory()->create([
        'brand_id' => Brand::factory()->create(['name' => 'Lattafa'])->id,
        'name' => 'Asad',
        'trash' => false,
    ]);

    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $byName = $this->tool->execute($user, ['query' => 'Sauvage', 'limit' => 10]);
    expect($byName['count'])->toBe(1)
        ->and($byName['products'][0]['name'])->toBe('Sauvage Elixir')
        ->and($byName['products'][0]['brand'])->toBe('Dior')
        ->and($byName['products'][0]['variants'][0]['sku'])->toBe('DIOR-SAV-100')
        ->and($byName['products'][0]['variants'][0]['primary_barcode'])->toBe('8901234567890');

    $encoded = json_encode($byName);
    expect($encoded)->not->toContain('current_stock')
        ->and($encoded)->not->toContain('purchase')
        ->and($encoded)->not->toContain('profit')
        ->and($encoded)->not->toContain('available_quantity')
        ->and($encoded)->not->toContain('99');

    $bySku = $this->tool->execute($user, ['query' => 'DIOR-SAV-100']);
    expect($bySku['count'])->toBe(1)->and($bySku['products'][0]['id'])->toBe($product->id);

    $byBarcode = $this->tool->execute($user, ['query' => '8901234567890']);
    expect($byBarcode['count'])->toBe(1)->and($byBarcode['products'][0]['id'])->toBe($product->id);

    $byBrand = $this->tool->execute($user, ['brand' => 'Dior']);
    expect($byBrand['count'])->toBe(1)->and($byBrand['products'][0]['brand'])->toBe('Dior');
});

test('search products excludes trashed products and variants', function () {
    $brand = Brand::factory()->create(['name' => 'Chanel']);
    $live = Product::factory()->create([
        'brand_id' => $brand->id,
        'name' => 'Bleu de Chanel',
        'trash' => false,
    ]);
    ProductVariant::factory()->create(['product_id' => $live->id, 'trash' => false]);

    $trashed = Product::factory()->create([
        'brand_id' => $brand->id,
        'name' => 'Bleu Trashed',
        'trash' => true,
    ]);
    ProductVariant::factory()->create(['product_id' => $trashed->id, 'trash' => true]);

    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $result = $this->tool->execute($user, ['query' => 'Bleu']);

    expect($result['count'])->toBe(1)
        ->and($result['products'][0]['id'])->toBe($live->id);
});

test('search products authorize requires products view permission', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

    expect($this->tool->authorize($admin))->toBeTrue()
        ->and($this->tool->authorize($cashier))->toBeFalse();
});

test('search products clamps limit and escapes like wildcards', function () {
    $brand = Brand::factory()->create(['name' => 'TestBrand']);
    Product::factory()->create([
        'brand_id' => $brand->id,
        'name' => '100% Oud',
        'trash' => false,
    ]);

    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $result = $this->tool->execute($user, [
        'query' => '100%',
        'limit' => 100,
    ]);

    expect($result['limit'])->toBe(SearchProductsTool::MAX_LIMIT)
        ->and($result['count'])->toBe(1)
        ->and($result['products'][0]['name'])->toBe('100% Oud');
});
