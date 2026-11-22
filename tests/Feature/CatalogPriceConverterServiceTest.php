<?php

use App\Models\Currency;
use App\Services\CatalogPriceConverter;
use App\Services\CurrencyService;
use App\Support\FxBoard;

beforeEach(function () {
    $this->afn = Currency::query()->where('code', 'AFN')->first()
        ?? Currency::factory()->create([
            'code' => 'AFN',
            'is_base' => true,
            'rate_to_default' => '1',
            'status' => 'active',
            'trash' => false,
        ]);

    $this->usd = Currency::query()->where('code', 'USD')->first();
    if ($this->usd) {
        $this->usd->update(['rate_to_default' => '65', 'status' => 'active', 'trash' => false, 'is_base' => false]);
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
        $this->aed->update(['rate_to_default' => '17.71117166', 'status' => 'active', 'trash' => false, 'is_base' => false]);
    } else {
        $this->aed = Currency::factory()->create([
            'code' => 'AED',
            'rate_to_default' => '17.71117166',
            'status' => 'active',
            'is_base' => false,
        ]);
    }

    $this->converter = app(CatalogPriceConverter::class);
});

test('catalog converter converts usd input into afn and aed with one board', function () {
    $converted = $this->converter->convertCatalogSet(
        [
            'purchase' => 100,
            'sale' => 150,
            'wholesale' => 130,
        ],
        $this->usd,
        'catalog_currency_id',
        [
            'usd_to_afn' => '65',
            'aed_to_afn' => '17.71117166',
            'source' => 'test',
        ],
    );

    expect($converted['currency_code'])->toBe('USD')
        ->and((float) $converted['purchase']['usd'])->toBe(100.0)
        ->and((float) $converted['purchase']['afn'])->toBe(6500.0)
        ->and((float) $converted['sale']['afn'])->toBe(9750.0)
        ->and((float) $converted['wholesale']['afn'])->toBe(8450.0)
        ->and($converted['snapshot']['catalog_usd_to_afn_rate'])->toBe('65.00000000');
});

test('catalog converter converts aed input into afn and usd', function () {
    $converted = $this->converter->convertCatalogSet(
        [
            'purchase' => 400,
            'sale' => 550,
            'wholesale' => 500,
        ],
        $this->aed,
        'catalog_currency_id',
        [
            'usd_to_afn' => '65',
            'usd_to_aed' => '3.67',
            'source' => 'test',
        ],
    );

    expect($converted['currency_code'])->toBe('AED')
        ->and((float) $converted['purchase']['aed'])->toBe(400.0)
        ->and((float) $converted['purchase']['afn'])->toBeGreaterThan(0)
        ->and((float) $converted['sale']['usd'])->toBeGreaterThan(0);
});

test('currency service board is reused and not a second fx system', function () {
    $service = app(CurrencyService::class);
    $board = $service->boardAt(null, [
        'usd_to_afn' => '65',
        'aed_to_afn' => '19',
        'source' => 'test',
    ], allowCatalogFallback: true);

    expect($board)->toBeInstanceOf(FxBoard::class)
        ->and($board->usdToAfn)->toBe('65.00000000')
        ->and($board->aedToAfn)->toBe('19.00000000');
});

test('independent converter converts each price field with its own currency on one board', function () {
    $converted = $this->converter->convertIndependentSet(
        [
            'purchase' => ['amount' => 100, 'currency' => $this->usd],
            'sale' => ['amount' => 550, 'currency' => $this->aed],
            'wholesale' => ['amount' => 8500, 'currency' => $this->afn],
        ],
        [
            'usd_to_afn' => '65',
            'usd_to_aed' => '3.67',
            'source' => 'test',
        ],
    );

    expect((int) $converted['purchase']['currency_id'])->toBe($this->usd->id)
        ->and((int) $converted['sale']['currency_id'])->toBe($this->aed->id)
        ->and((int) $converted['wholesale']['currency_id'])->toBe($this->afn->id)
        ->and((float) $converted['purchase']['afn'])->toBe(6500.0)
        ->and((float) $converted['purchase']['usd'])->toBe(100.0)
        ->and((float) $converted['sale']['aed'])->toBe(550.0)
        ->and((float) $converted['wholesale']['afn'])->toBe(8500.0)
        ->and($converted['board'])->toBeInstanceOf(FxBoard::class)
        ->and($converted['snapshot']['catalog_usd_to_afn_rate'])->toBe('65.00000000');
});
