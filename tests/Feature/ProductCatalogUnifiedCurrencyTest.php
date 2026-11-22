<?php

use App\Models\Brand;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    expect(config('database.connections.mysql.database') ?: config('database.connections.'.config('database.default').'.database'))
        ->toBe('zuha_testing');

    $branch = Branch::factory()->create();
    $this->admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'branch_id' => $branch->id,
    ]);

    $this->afn = Currency::query()->where('code', 'AFN')->first()
        ?? Currency::factory()->create([
            'code' => 'AFN',
            'is_base' => true,
            'rate_to_default' => '1',
            'status' => 'active',
        ]);

    $this->usd = Currency::query()->where('code', 'USD')->first();
    if ($this->usd) {
        $this->usd->update(['rate_to_default' => '65', 'status' => 'active', 'trash' => false]);
    } else {
        $this->usd = Currency::factory()->create([
            'code' => 'USD',
            'rate_to_default' => '65',
            'status' => 'active',
            'is_base' => false,
        ]);
    }

    $this->aed = Currency::query()->where('code', 'AED')->first();
    if ($this->aed) {
        $this->aed->update(['rate_to_default' => '17.71117166', 'status' => 'active', 'trash' => false]);
    } else {
        $this->aed = Currency::factory()->create([
            'code' => 'AED',
            'rate_to_default' => '17.71117166',
            'status' => 'active',
            'is_base' => false,
        ]);
    }
});

test('creating a product with afn catalog currency stores original and three-currency equivalents', function () {
    $this->actingAs($this->admin);
    $brand = Brand::factory()->create();

    $this->post('/products', [
        'name' => 'Unified AFN Catalog',
        'brand_id' => $brand->id,
        'gender' => 'unisex',
        'status' => 'active',
        'usd_to_afn_rate' => '65',
        'usd_to_aed_rate' => '3.67',
        'variants' => [
            [
                'volume_ml' => 100,
                'catalog_currency_id' => $this->afn->id,
                'purchase_amount' => 7000,
                'sale_amount' => 9000,
                'wholesale_amount' => 8500,
                'wholesale_min_qty' => 3,
                'sale_price_afn' => 9000,
                'barcodes' => [
                    ['barcode' => '1110001110001', 'barcode_type' => 'EAN13', 'is_primary' => true],
                ],
            ],
        ],
    ])->assertRedirect('/products');

    $product = Product::query()->where('name', 'Unified AFN Catalog')->whereNull('variant_id')->first();
    expect($product)->not->toBeNull();

    $variant = ProductVariant::query()->where('product_id', $product->id)->first();
    expect($variant)->not->toBeNull()
        ->and((int) $variant->catalog_currency_id)->toBe($this->afn->id)
        ->and((int) $variant->purchase_currency_id)->toBe($this->afn->id)
        ->and((int) $variant->sale_currency_id)->toBe($this->afn->id)
        ->and((int) $variant->wholesale_currency_id)->toBe($this->afn->id)
        ->and((float) $variant->purchase_amount)->toBe(7000.0)
        ->and((float) $variant->sale_amount)->toBe(9000.0)
        ->and((float) $variant->wholesale_amount)->toBe(8500.0)
        ->and((float) $variant->purchase_price_afn)->toBe(7000.0)
        ->and((float) $variant->sale_price_afn)->toBe(9000.0)
        ->and((float) $variant->wholesale_price_afn)->toBe(8500.0)
        ->and((float) $variant->purchase_amount_usd)->toBe(107.69)
        ->and((float) $variant->sale_amount_usd)->toBe(138.46)
        ->and((float) $variant->wholesale_amount_usd)->toBe(130.77)
        ->and($variant->catalog_usd_to_afn_rate)->not->toBeNull()
        ->and($variant->catalog_fx_converted_at)->not->toBeNull();

    expect(ProductPrice::query()->where('variant_id', $variant->id)->where('price_type', 'sale')->count())
        ->toBeGreaterThanOrEqual(3);
});

test('creating a product with usd catalog currency calculates afn and aed equivalents', function () {
    $this->actingAs($this->admin);
    $brand = Brand::factory()->create();

    $this->post('/products', [
        'name' => 'Unified USD Catalog',
        'brand_id' => $brand->id,
        'gender' => 'unisex',
        'status' => 'active',
        'usd_to_afn_rate' => '65',
        'usd_to_aed_rate' => '3.67',
        'variants' => [
            [
                'volume_ml' => 50,
                'catalog_currency_id' => $this->usd->id,
                'purchase_amount' => 100,
                'sale_amount' => 150,
                'wholesale_amount' => 130,
                'wholesale_min_qty' => 3,
                'sale_price_afn' => 150,
                'barcodes' => [
                    ['barcode' => '2220002220002', 'barcode_type' => 'EAN13', 'is_primary' => true],
                ],
            ],
        ],
    ])->assertRedirect('/products');

    $variant = ProductVariant::query()
        ->whereHas('product', fn ($q) => $q->where('name', 'Unified USD Catalog')->whereNull('variant_id'))
        ->first();

    expect($variant)->not->toBeNull()
        ->and((int) $variant->catalog_currency_id)->toBe($this->usd->id)
        ->and((float) $variant->purchase_amount)->toBe(100.0)
        ->and((float) $variant->purchase_price_afn)->toBe(6500.0)
        ->and((float) $variant->sale_price_afn)->toBe(9750.0)
        ->and((float) $variant->purchase_amount_usd)->toBe(100.0)
        ->and((float) $variant->sale_amount_usd)->toBe(150.0);
});

test('editing catalog currency recalculates equivalents without touching purchase batches', function () {
    $this->actingAs($this->admin);
    $brand = Brand::factory()->create();

    $this->post('/products', [
        'name' => 'Edit Currency Product',
        'brand_id' => $brand->id,
        'gender' => 'unisex',
        'status' => 'active',
        'usd_to_afn_rate' => '65',
        'usd_to_aed_rate' => '3.67',
        'variants' => [
            [
                'volume_ml' => 100,
                'catalog_currency_id' => $this->afn->id,
                'purchase_amount' => 7000,
                'sale_amount' => 9000,
                'wholesale_amount' => 8500,
                'wholesale_min_qty' => 3,
                'sale_price_afn' => 9000,
                'barcodes' => [
                    ['barcode' => '3330003330003', 'barcode_type' => 'EAN13', 'is_primary' => true],
                ],
            ],
        ],
    ])->assertRedirect('/products');

    $product = Product::query()->where('name', 'Edit Currency Product')->whereNull('variant_id')->firstOrFail();
    $variant = ProductVariant::query()->where('product_id', $product->id)->firstOrFail();

    // Historical purchase snapshot created outside the product form.
    $batch = Product::factory()->create([
        'name' => $product->name,
        'brand_id' => $brand->id,
        'variant_id' => $variant->id,
        'purchase_price_afn' => 12345.67,
        'quantity' => 5,
        'remaining_quantity' => 5,
        'trash' => false,
        'usd_to_afn_rate' => '70.00000000',
        'aed_to_afn_rate' => '19.00000000',
        'rate_source' => 'historical_test',
    ]);
    $originalBatchCost = (float) $batch->purchase_price_afn;
    $originalUsdRate = (string) $batch->usd_to_afn_rate;

    // Catalog-only recalculation via the same converter ProductController uses.
    // Does not invoke purchase/sale sync paths.
    $converted = app(\App\Services\CatalogPriceConverter::class)->convertCatalogSet(
        [
            'purchase' => 100,
            'sale' => 150,
            'wholesale' => 130,
        ],
        $this->usd,
        'catalog_currency_id',
        [
            'usd_to_afn' => '65',
            'usd_to_aed' => '3.67',
            'source' => 'product_reference',
        ],
    );

    $variant->update([
        'catalog_currency_id' => $converted['currency_id'],
        'purchase_currency_id' => $converted['currency_id'],
        'sale_currency_id' => $converted['currency_id'],
        'wholesale_currency_id' => $converted['currency_id'],
        'purchase_amount' => $converted['purchase']['amount'],
        'sale_amount' => $converted['sale']['amount'],
        'wholesale_amount' => $converted['wholesale']['amount'],
        'purchase_price_afn' => $converted['purchase']['afn'],
        'sale_price_afn' => $converted['sale']['afn'],
        'wholesale_price_afn' => $converted['wholesale']['afn'],
        'purchase_amount_usd' => $converted['purchase']['usd'],
        'sale_amount_usd' => $converted['sale']['usd'],
        'wholesale_amount_usd' => $converted['wholesale']['usd'],
        'purchase_amount_aed' => $converted['purchase']['aed'],
        'sale_amount_aed' => $converted['sale']['aed'],
        'wholesale_amount_aed' => $converted['wholesale']['aed'],
        ...$converted['snapshot'],
    ]);

    $variant->refresh();
    $batch->refresh();

    expect((int) $variant->catalog_currency_id)->toBe($this->usd->id)
        ->and((float) $variant->purchase_price_afn)->toBe(6500.0)
        ->and((float) $variant->sale_price_afn)->toBe(9750.0)
        ->and((float) $variant->purchase_amount_usd)->toBe(100.0)
        ->and((float) $batch->purchase_price_afn)->toBe($originalBatchCost)
        ->and((string) $batch->usd_to_afn_rate)->toBe($originalUsdRate)
        ->and($batch->trash)->toBeFalse();
});

test('product edit http path can change catalog currency and store equivalents', function () {
    $this->actingAs($this->admin);
    $brand = Brand::factory()->create();

    $this->post('/products', [
        'name' => 'HTTP Edit Catalog',
        'brand_id' => $brand->id,
        'gender' => 'unisex',
        'status' => 'active',
        'usd_to_afn_rate' => '65',
        'usd_to_aed_rate' => '3.67',
        'variants' => [
            [
                'volume_ml' => 100,
                'catalog_currency_id' => $this->afn->id,
                'purchase_amount' => 7000,
                'sale_amount' => 9000,
                'wholesale_amount' => 8500,
                'sale_price_afn' => 9000,
                'barcodes' => [
                    ['barcode' => '6660006660006', 'barcode_type' => 'EAN13', 'is_primary' => true],
                ],
            ],
        ],
    ])->assertRedirect('/products');

    $product = Product::query()->where('name', 'HTTP Edit Catalog')->whereNull('variant_id')->firstOrFail();
    $variant = ProductVariant::query()->where('product_id', $product->id)->firstOrFail();

    $this->put("/products/{$product->id}", [
        'name' => $product->name,
        'brand_id' => $brand->id,
        'gender' => 'unisex',
        'status' => 'active',
        'usd_to_afn_rate' => '65',
        'usd_to_aed_rate' => '3.67',
        'variants' => [
            [
                'id' => $variant->id,
                'volume_ml' => 100,
                'catalog_currency_id' => $this->usd->id,
                'purchase_amount' => 100,
                'sale_amount' => 150,
                'wholesale_amount' => 130,
                'wholesale_min_qty' => 3,
                'sale_price_afn' => 150,
                'barcodes' => [
                    ['barcode' => '6660006660006', 'barcode_type' => 'EAN13', 'is_primary' => true],
                ],
            ],
        ],
    ])->assertRedirect();

    $variant->refresh();

    expect((int) $variant->catalog_currency_id)->toBe($this->usd->id)
        ->and((float) $variant->purchase_price_afn)->toBe(6500.0)
        ->and((float) $variant->sale_price_afn)->toBe(9750.0)
        ->and((float) $variant->sale_amount_usd)->toBe(150.0);
});

test('creating a product with independent currencies per price field stores originals and equivalents', function () {
    $this->actingAs($this->admin);
    $brand = Brand::factory()->create();

    $this->post('/products', [
        'name' => 'Independent Currencies Product',
        'brand_id' => $brand->id,
        'gender' => 'unisex',
        'status' => 'active',
        'usd_to_afn_rate' => '65',
        'usd_to_aed_rate' => '3.67',
        'variants' => [
            [
                'volume_ml' => 100,
                'purchase_currency_id' => $this->usd->id,
                'sale_currency_id' => $this->aed->id,
                'wholesale_currency_id' => $this->afn->id,
                'purchase_amount' => 100,
                'sale_amount' => 550,
                'wholesale_amount' => 8500,
                'wholesale_min_qty' => 3,
                'sale_price_afn' => 550,
                'barcodes' => [
                    ['barcode' => '4440004440004', 'barcode_type' => 'EAN13', 'is_primary' => true],
                ],
            ],
        ],
    ])->assertRedirect('/products');

    $variant = ProductVariant::query()
        ->whereHas('product', fn ($q) => $q->where('name', 'Independent Currencies Product')->whereNull('variant_id'))
        ->first();

    expect($variant)->not->toBeNull()
        ->and((int) $variant->purchase_currency_id)->toBe($this->usd->id)
        ->and((int) $variant->sale_currency_id)->toBe($this->aed->id)
        ->and((int) $variant->wholesale_currency_id)->toBe($this->afn->id)
        ->and((int) $variant->catalog_currency_id)->toBe($this->aed->id)
        ->and((float) $variant->purchase_amount)->toBe(100.0)
        ->and((float) $variant->purchase_price_afn)->toBe(6500.0)
        ->and((float) $variant->purchase_amount_usd)->toBe(100.0)
        ->and((float) $variant->sale_amount)->toBe(550.0)
        ->and((float) $variant->sale_amount_aed)->toBe(550.0)
        ->and((float) $variant->sale_price_afn)->toBeGreaterThan(0)
        ->and((float) $variant->wholesale_amount)->toBe(8500.0)
        ->and((float) $variant->wholesale_price_afn)->toBe(8500.0)
        ->and($variant->hasMixedCatalogCurrencies())->toBeTrue()
        ->and($variant->catalog_fx_converted_at)->not->toBeNull();
});

test('legacy mixed currency variants remain readable without forcing unification', function () {
    if (! Schema::hasColumn('product_variants', 'catalog_currency_id')) {
        $this->markTestSkipped('catalog_currency_id column missing');
    }

    $brand = Brand::factory()->create();
    $product = Product::factory()->create([
        'name' => 'Legacy Mixed',
        'brand_id' => $brand->id,
        'variant_id' => null,
        'trash' => false,
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'catalog_currency_id' => null,
        'purchase_currency_id' => $this->usd->id,
        'sale_currency_id' => $this->afn->id,
        'wholesale_currency_id' => $this->aed->id,
        'purchase_amount' => 10,
        'sale_amount' => 9000,
        'wholesale_amount' => 400,
        'purchase_price_afn' => 650,
        'sale_price_afn' => 9000,
        'wholesale_price_afn' => 7000,
        'trash' => false,
    ]);

    expect($variant->hasMixedCatalogCurrencies())->toBeTrue()
        ->and($variant->catalog_currency_id)->toBeNull()
        ->and((int) $variant->resolvedCatalogCurrencyId())->toBe($this->afn->id);
});

test('changing catalog rates does not rewrite unrelated ledger tables', function () {
    $beforeSales = Schema::hasTable('sales') ? DB::table('sales')->count() : 0;
    $beforePurchases = Product::query()->whereNotNull('variant_id')->count();

    $this->actingAs($this->admin);
    $brand = Brand::factory()->create();

    $this->post('/products', [
        'name' => 'Isolation Check',
        'brand_id' => $brand->id,
        'gender' => 'unisex',
        'status' => 'active',
        'usd_to_afn_rate' => '65',
        'usd_to_aed_rate' => '3.67',
        'variants' => [
            [
                'catalog_currency_id' => $this->afn->id,
                'purchase_amount' => 100,
                'sale_amount' => 200,
                'wholesale_amount' => 150,
                'sale_price_afn' => 200,
                'barcodes' => [
                    ['barcode' => '5550005550005', 'barcode_type' => 'EAN13', 'is_primary' => true],
                ],
            ],
        ],
    ])->assertRedirect('/products');

    $afterSales = Schema::hasTable('sales') ? DB::table('sales')->count() : 0;
    $afterPurchases = Product::query()->whereNotNull('variant_id')->count();

    expect($afterSales)->toBe($beforeSales)
        ->and($afterPurchases)->toBe($beforePurchases);
});
