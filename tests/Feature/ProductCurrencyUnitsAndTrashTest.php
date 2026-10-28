<?php

use App\Models\Brand;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductExchangeRate;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ProductExchangeRateService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $branch = Branch::factory()->create();
    $this->admin = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'branch_id' => $branch->id,
    ]);
});

test('admins can open product trash after a product is deleted', function () {
    $this->actingAs($this->admin);

    $product = Product::factory()->create(['variant_id' => null, 'trash' => false]);

    $this->delete("/products/{$product->id}")->assertRedirect('/products');

    $this->get('/products/trash')->assertOk();

    expect(Product::query()->find($product->id)?->trash)->toBeTrue();

    $this->post("/products/{$product->id}/restore")->assertRedirect('/products');

    expect(Product::query()->find($product->id)?->trash)->toBeFalse();
});

test('product barcode lookup is routed', function () {
    $this->actingAs($this->admin);

    $this->get('/products/check-barcode?barcode=1234567890123')
        ->assertOk()
        ->assertJson(['exists' => false]);
});

test('creating a product stores entered currency amounts and ledger snapshots', function () {
    $this->actingAs($this->admin);

    $afn = Currency::query()->where('code', 'AFN')->first()
        ?? Currency::factory()->create(['code' => 'AFN', 'is_base' => true, 'rate_to_default' => '1', 'status' => 'active']);
    $usd = Currency::query()->where('code', 'USD')->first();
    if (! $usd) {
        $usd = Currency::factory()->create(['code' => 'USD', 'rate_to_default' => '70', 'status' => 'active', 'is_base' => false]);
    } else {
        $usd->update(['rate_to_default' => '70', 'status' => 'active', 'trash' => false]);
    }

    $brand = Brand::factory()->create();

    $this->post('/products', [
        'name' => 'Currency Perfume',
        'brand_id' => $brand->id,
        'gender' => 'unisex',
        'status' => 'active',
        'base_unit_name' => 'bottle',
        'pack_unit_name' => 'box',
        'units_per_pack' => '12',
        'variants' => [
            [
                'volume_ml' => 100,
                'purchase_price_afn' => 10,
                'purchase_amount' => 10,
                'purchase_currency_id' => $usd->id,
                'sale_price_afn' => 15,
                'sale_amount' => 15,
                'sale_currency_id' => $usd->id,
                'wholesale_price_afn' => 12,
                'wholesale_amount' => 12,
                'wholesale_currency_id' => $usd->id,
                'barcodes' => [
                    ['barcode' => '9990001112223', 'barcode_type' => 'EAN13', 'is_primary' => true],
                ],
            ],
        ],
    ])->assertRedirect('/products');

    $product = Product::query()->where('name', 'Currency Perfume')->whereNull('variant_id')->first();

    expect($product)->not->toBeNull()
        ->and($product->base_unit_name)->toBe('bottle')
        ->and($product->pack_unit_name)->toBe('box')
        ->and((float) $product->units_per_pack)->toBe(12.0)
        ->and($product->conversionLabel())->toBe('1 box = 12 bottle');

    $variant = ProductVariant::query()->where('product_id', $product->id)->first();

    expect($variant)->not->toBeNull()
        ->and((int) $variant->purchase_currency_id)->toBe($usd->id)
        ->and((float) $variant->purchase_amount)->toBe(10.0)
        ->and((float) $variant->purchase_price_afn)->toBe(700.0)
        ->and((float) $variant->sale_price_afn)->toBe(1050.0);
});

test('creating a product stores usd to afn and usd to aed rates as afn and aed per one usd', function () {
    $this->actingAs($this->admin);

    $usd = Currency::query()->where('code', 'USD')->first()
        ?? Currency::factory()->create(['code' => 'USD', 'rate_to_default' => '70', 'status' => 'active']);
    $afn = Currency::query()->where('code', 'AFN')->first()
        ?? Currency::factory()->create(['code' => 'AFN', 'is_base' => true, 'rate_to_default' => '1', 'status' => 'active']);
    $aed = Currency::query()->where('code', 'AED')->first()
        ?? Currency::factory()->create(['code' => 'AED', 'rate_to_default' => '19', 'status' => 'active']);

    $brand = Brand::factory()->create();

    $this->post('/products', [
        'name' => 'Rate Perfume',
        'brand_id' => $brand->id,
        'gender' => 'unisex',
        'status' => 'active',
        'usd_to_afn_rate' => '70',
        'usd_to_aed_rate' => '3.6725',
        'variants' => [
            [
                'volume_ml' => 50,
                'purchase_price_afn' => 10,
                'sale_price_afn' => 20,
                'barcodes' => [
                    ['barcode' => '8880001112223', 'barcode_type' => 'EAN13', 'is_primary' => true],
                ],
            ],
        ],
    ])->assertRedirect('/products');

    $product = Product::query()->where('name', 'Rate Perfume')->whereNull('variant_id')->first();
    $rates = app(ProductExchangeRateService::class);

    expect($product)->not->toBeNull()
        ->and($rates->rate($product, 'USD', 'AFN'))->toBe('70')
        ->and($rates->rate($product, 'USD', 'AED'))->toBe('3.6725')
        ->and((int) ProductExchangeRate::query()->where('product_id', $product->id)->count())->toBe(2);

    $this->put("/products/{$product->id}", [
        'name' => 'Rate Perfume',
        'brand_id' => $brand->id,
        'gender' => 'unisex',
        'status' => 'active',
        'usd_to_afn_rate' => '70',
        'usd_to_aed_rate' => '3.6725',
        'variants' => [
            [
                'id' => $product->variants()->value('id'),
                'volume_ml' => 50,
                'purchase_price_afn' => 10,
                'sale_price_afn' => 20,
            ],
        ],
    ])->assertRedirect('/products');

    expect($product->fresh()->exchange_rate)->toBeNull();
});

test('product exchange rates reject zero and negative values', function () {
    $this->actingAs($this->admin);
    $brand = Brand::factory()->create();

    $this->from('/products/create')->post('/products', [
        'name' => 'Bad Rate Perfume',
        'brand_id' => $brand->id,
        'gender' => 'unisex',
        'status' => 'active',
        'usd_to_afn_rate' => '0',
        'usd_to_aed_rate' => '-3',
        'variants' => [
            [
                'volume_ml' => 50,
                'purchase_price_afn' => 10,
                'sale_price_afn' => 20,
                'barcodes' => [
                    ['barcode' => '7770001112223', 'barcode_type' => 'EAN13', 'is_primary' => true],
                ],
            ],
        ],
    ])->assertRedirect('/products/create')
        ->assertSessionHasErrors(['usd_to_afn_rate', 'usd_to_aed_rate']);
});

test('pack quantity conversion multiplies by the defined factor', function () {
    $product = Product::factory()->create([
        'variant_id' => null,
        'base_unit_name' => 'bottle',
        'pack_unit_name' => 'box',
        'units_per_pack' => '12',
    ]);

    expect($product->quantityToBaseUnits(2, 'pack'))->toBe(24);
});
