<?php

use App\Models\Brand;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\ExchangeService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Schema;

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

    expect($product)->not->toBeNull();

    $variant = ProductVariant::query()->where('product_id', $product->id)->first();

    expect($variant)->not->toBeNull()
        ->and((int) $variant->purchase_currency_id)->toBe($usd->id)
        ->and((float) $variant->purchase_amount)->toBe(10.0)
        ->and((float) $variant->purchase_price_afn)->toBe(700.0)
        ->and((float) $variant->sale_price_afn)->toBe(1050.0);
});

test('creating a product does not store product-level exchange rates; board is the single source', function () {
    $this->actingAs($this->admin);

    $usd = Currency::query()->where('code', 'USD')->first()
        ?? Currency::factory()->create(['code' => 'USD', 'rate_to_default' => '70', 'status' => 'active']);
    $afn = Currency::query()->where('code', 'AFN')->first()
        ?? Currency::factory()->create(['code' => 'AFN', 'is_base' => true, 'rate_to_default' => '1', 'status' => 'active']);
    $aed = Currency::query()->where('code', 'AED')->first()
        ?? Currency::factory()->create(['code' => 'AED', 'rate_to_default' => '19', 'status' => 'active']);

    app(CurrencyService::class)->postRate($usd, $afn, '70', now(), null, 'test');
    app(CurrencyService::class)->postRate($aed, $afn, '19', now(), null, 'test');

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
    $board = app(ExchangeService::class)->getCurrentRates();

    expect($product)->not->toBeNull()
        ->and((float) $board['usd_to_afn'])->toBe(70.0)
        ->and(Schema::hasTable('product_exchange_rates'))->toBeFalse();

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

test('product edit page loads fx board and later board rate changes do not rewrite purchase FX snapshots', function () {
    $this->actingAs($this->admin);

    $afn = Currency::query()->where('code', 'AFN')->first()
        ?? Currency::factory()->create(['code' => 'AFN', 'is_base' => true, 'rate_to_default' => '1', 'status' => 'active']);
    $usd = Currency::query()->where('code', 'USD')->first()
        ?? Currency::factory()->create(['code' => 'USD', 'rate_to_default' => '70', 'status' => 'active', 'is_base' => false]);
    $aed = Currency::query()->where('code', 'AED')->first()
        ?? Currency::factory()->create(['code' => 'AED', 'rate_to_default' => '19', 'status' => 'active', 'is_base' => false]);
    $usd->update(['rate_to_default' => '70', 'status' => 'active', 'trash' => false]);

    $fx = app(CurrencyService::class);
    $fx->postRate($usd, $afn, '70', now()->subDay(), null, 'test');
    $fx->postRate($aed, $afn, '19', now()->subDay(), null, 'test');

    $brand = Brand::factory()->create();

    $this->post('/products', [
        'name' => 'Edit Estimate Perfume',
        'brand_id' => $brand->id,
        'gender' => 'unisex',
        'status' => 'active',
        'variants' => [
            [
                'volume_ml' => 100,
                'purchase_amount' => 10,
                'purchase_price_afn' => 10,
                'purchase_currency_id' => $usd->id,
                'sale_amount' => 20,
                'sale_price_afn' => 20,
                'sale_currency_id' => $usd->id,
                'wholesale_amount' => 15,
                'wholesale_price_afn' => 15,
                'wholesale_currency_id' => $usd->id,
                'barcodes' => [
                    ['barcode' => '6660001112223', 'barcode_type' => 'EAN13', 'is_primary' => true],
                ],
            ],
        ],
        'purchases' => [
            [
                'variant_index' => 0,
                'investor_id' => 'store',
                'quantity' => 5,
                'purchase_date' => now()->toDateString(),
                'payment_status' => 'paid',
                'usd_to_afn_rate' => '70',
                'aed_to_afn_rate' => '19',
            ],
        ],
    ])->assertRedirect('/products');

    $product = Product::query()->where('name', 'Edit Estimate Perfume')->whereNull('variant_id')->first();
    expect($product)->not->toBeNull();

    $purchaseRow = Product::query()
        ->whereNotNull('variant_id')
        ->where('name', $product->name)
        ->where('brand_id', $product->brand_id)
        ->first();

    expect($purchaseRow)->not->toBeNull();
    $snapshotRate = (float) ($purchaseRow->exchange_rate ?? 0);
    $snapshotLine = (float) ($purchaseRow->purchase_line_amount ?? 0);
    $snapshotCode = $purchaseRow->purchase_currency_code;

    $this->get("/products/{$product->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('products/edit')
            ->has('fxBoard')
            ->has('currencies')
            ->missing('usd_to_afn_rate')
            ->missing('usd_to_aed_rate')
            ->missing('exchangeRateContext')
        );

    $variantId = $product->variants()->value('id');

    // New board rate must not rewrite the historical purchase snapshot.
    $fx->postRate($usd, $afn, '80', now(), null, 'test');

    $this->put("/products/{$product->id}", [
        'name' => 'Edit Estimate Perfume',
        'brand_id' => $brand->id,
        'gender' => 'unisex',
        'status' => 'active',
        'variants' => [
            [
                'id' => $variantId,
                'volume_ml' => 100,
                'purchase_amount' => 10,
                'purchase_price_afn' => 10,
                'purchase_currency_id' => $usd->id,
                'sale_amount' => 20,
                'sale_price_afn' => 20,
                'sale_currency_id' => $usd->id,
                'wholesale_amount' => 15,
                'wholesale_price_afn' => 15,
                'wholesale_currency_id' => $usd->id,
                'barcodes' => [
                    ['id' => ProductVariant::find($variantId)?->barcodes()->value('id'), 'barcode' => '6660001112223', 'barcode_type' => 'EAN13', 'is_primary' => true],
                ],
            ],
        ],
        'purchases' => [
            [
                'id' => $purchaseRow->id,
                'variant_index' => 0,
                'investor_id' => 'store',
                'quantity' => 5,
                'purchase_date' => $purchaseRow->purchase_date?->toDateString() ?? now()->toDateString(),
                'payment_status' => 'paid',
            ],
        ],
    ])->assertRedirect('/products');

    $purchaseRow->refresh();

    expect((float) app(ExchangeService::class)->getRate('USD', 'AFN'))->toBe(80.0)
        ->and((float) $purchaseRow->exchange_rate)->toBe($snapshotRate)
        ->and((float) ($purchaseRow->purchase_line_amount ?? 0))->toBe($snapshotLine)
        ->and($purchaseRow->purchase_currency_code)->toBe($snapshotCode);
});
