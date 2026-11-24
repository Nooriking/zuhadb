<?php

use App\Models\Brand;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

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
    $this->afn->update(['is_base' => true, 'rate_to_default' => '1', 'status' => 'active', 'trash' => false]);

    $this->usd = Currency::query()->where('code', 'USD')->first()
        ?? Currency::factory()->create([
            'code' => 'USD',
            'rate_to_default' => '65',
            'status' => 'active',
            'is_base' => false,
        ]);
    $this->usd->update(['rate_to_default' => '65', 'status' => 'active', 'trash' => false, 'is_base' => false]);

    $this->aed = Currency::query()->where('code', 'AED')->first()
        ?? Currency::factory()->create([
            'code' => 'AED',
            'rate_to_default' => '17.71117166',
            'status' => 'active',
            'is_base' => false,
        ]);
    $this->aed->update(['rate_to_default' => '17.71117166', 'status' => 'active', 'trash' => false, 'is_base' => false]);

    \Illuminate\Support\Facades\DB::table('exchange_rates')->delete();

    $usdRateId = \Illuminate\Support\Facades\DB::table('exchange_rates')->insertGetId([
        'from_currency_id' => $this->usd->id,
        'to_currency_id' => $this->afn->id,
        'rate' => '65.00000000',
        'effective_at' => now()->subDay(),
        'source' => 'test',
        'trash' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    \Illuminate\Support\Facades\DB::table('exchange_rates')->insert([
        'from_currency_id' => $this->aed->id,
        'to_currency_id' => $this->afn->id,
        'rate' => '17.71117166',
        'effective_at' => now()->subDay(),
        'source' => 'test',
        'trash' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(\Illuminate\Support\Facades\DB::table('exchange_rates')->where('id', $usdRateId)->exists())->toBeTrue();
});

test('purchase sale and wholesale currencies stay independent and mark original rows', function () {
    $this->actingAs($this->admin);
    $brand = Brand::factory()->create();

    $this->post('/products', [
        'name' => 'Independent FX Product',
        'brand_id' => $brand->id,
        'gender' => 'unisex',
        'status' => 'active',
        'variants' => [
            [
                'volume_ml' => 100,
                'purchase_currency_id' => $this->usd->id,
                'sale_currency_id' => $this->afn->id,
                'wholesale_currency_id' => $this->aed->id,
                'purchase_amount' => 100,
                'purchase_price_afn' => 100,
                'sale_amount' => 8000,
                'sale_price_afn' => 8000,
                'wholesale_amount' => 250,
                'wholesale_price_afn' => 250,
                'wholesale_min_qty' => 5,
                'barcodes' => [
                    ['barcode' => 'IND0000000001', 'barcode_type' => 'EAN13', 'is_primary' => true],
                ],
            ],
        ],
    ])->assertRedirect('/products');

    $product = Product::query()->where('name', 'Independent FX Product')->orderByDesc('id')->first();
    expect($product)->not->toBeNull();

    $variant = ProductVariant::query()->where('product_id', $product->id)->first();
    expect($variant)->not->toBeNull();

    $purchaseOriginal = ProductPrice::query()
        ->where('variant_id', $variant->id)
        ->where('price_type', 'purchase')
        ->where('is_original', true)
        ->first();
    $saleOriginal = ProductPrice::query()
        ->where('variant_id', $variant->id)
        ->where('price_type', 'sale')
        ->where('is_original', true)
        ->first();
    $wholesaleOriginal = ProductPrice::query()
        ->where('variant_id', $variant->id)
        ->where('price_type', 'wholesale')
        ->where('is_original', true)
        ->first();

    expect($purchaseOriginal)->not->toBeNull()
        ->and((int) $purchaseOriginal->currency_id)->toBe($this->usd->id)
        ->and((float) $purchaseOriginal->amount)->toBe(100.0)
        ->and($saleOriginal)->not->toBeNull()
        ->and((int) $saleOriginal->currency_id)->toBe($this->afn->id)
        ->and((float) $saleOriginal->amount)->toBe(8000.0)
        ->and($wholesaleOriginal)->not->toBeNull()
        ->and((int) $wholesaleOriginal->currency_id)->toBe($this->aed->id)
        ->and((float) $wholesaleOriginal->amount)->toBe(250.0);

    // Equivalents exist for PriceResolver without changing originals.
    expect(ProductPrice::query()->where('variant_id', $variant->id)->where('price_type', 'purchase')->count())
        ->toBeGreaterThanOrEqual(3);
});

test('changing only purchase currency on update does not rewrite sale original currency', function () {
    $this->actingAs($this->admin);
    $brand = Brand::factory()->create();

    $this->post('/products', [
        'name' => 'Independent Update Product',
        'brand_id' => $brand->id,
        'gender' => 'unisex',
        'status' => 'active',
        'variants' => [
            [
                'purchase_currency_id' => $this->usd->id,
                'sale_currency_id' => $this->afn->id,
                'wholesale_currency_id' => $this->aed->id,
                'purchase_amount' => 100,
                'purchase_price_afn' => 100,
                'sale_amount' => 8000,
                'sale_price_afn' => 8000,
                'wholesale_amount' => 250,
                'wholesale_price_afn' => 250,
                'wholesale_min_qty' => 5,
                'barcodes' => [
                    ['barcode' => 'IND0000000002', 'barcode_type' => 'EAN13', 'is_primary' => true],
                ],
            ],
        ],
    ])->assertRedirect('/products');

    $product = Product::query()->where('name', 'Independent Update Product')->orderByDesc('id')->first();
    expect($product)->not->toBeNull();
    $variant = ProductVariant::query()->where('product_id', $product->id)->first();
    expect($variant)->not->toBeNull();

    $this->put('/products/'.$product->id, [
        'name' => 'Independent Update Product',
        'brand_id' => $brand->id,
        'gender' => 'unisex',
        'status' => 'active',
        'variants' => [
            [
                'id' => $variant->id,
                'purchase_currency_id' => $this->aed->id,
                'sale_currency_id' => $this->afn->id,
                'wholesale_currency_id' => $this->aed->id,
                'purchase_amount' => 400,
                'purchase_price_afn' => 400,
                'sale_amount' => 8000,
                'sale_price_afn' => 8000,
                'wholesale_amount' => 250,
                'wholesale_price_afn' => 250,
                'wholesale_min_qty' => 5,
                'barcodes' => [
                    ['barcode' => 'IND0000000002', 'barcode_type' => 'EAN13', 'is_primary' => true],
                ],
            ],
        ],
        'purchases' => [],
    ])->assertRedirect();

    $purchaseOriginal = ProductPrice::query()
        ->where('variant_id', $variant->id)
        ->where('price_type', 'purchase')
        ->where('is_original', true)
        ->first();
    $saleOriginal = ProductPrice::query()
        ->where('variant_id', $variant->id)
        ->where('price_type', 'sale')
        ->where('is_original', true)
        ->first();

    expect($purchaseOriginal)->not->toBeNull()
        ->and((int) $purchaseOriginal->currency_id)->toBe($this->aed->id)
        ->and((float) $purchaseOriginal->amount)->toBe(400.0)
        ->and($saleOriginal)->not->toBeNull()
        ->and((int) $saleOriginal->currency_id)->toBe($this->afn->id)
        ->and((float) $saleOriginal->amount)->toBe(8000.0);
});
