<?php

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Models\User;
use App\Services\ExchangeService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

test('exchange service reads only exchange_rates and observers sync cache plus history', function () {
    $afn = Currency::query()->where('code', 'AFN')->first()
        ?? Currency::factory()->create(['code' => 'AFN', 'is_base' => true, 'rate_to_default' => '1', 'status' => 'active']);
    $usd = Currency::query()->where('code', 'USD')->first()
        ?? Currency::factory()->create(['code' => 'USD', 'rate_to_default' => '1', 'status' => 'active', 'is_base' => false]);
    $aed = Currency::query()->where('code', 'AED')->first()
        ?? Currency::factory()->create(['code' => 'AED', 'rate_to_default' => '1', 'status' => 'active', 'is_base' => false]);

    $afn->update(['is_base' => true, 'rate_to_default' => '1', 'status' => 'active', 'trash' => false]);
    $usd->update(['rate_to_default' => '1', 'status' => 'active', 'trash' => false, 'is_base' => false]);
    $aed->update(['rate_to_default' => '1', 'status' => 'active', 'trash' => false, 'is_base' => false]);

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $usdRate = ExchangeRate::create([
        'from_currency_id' => $usd->id,
        'to_currency_id' => $afn->id,
        'rate' => '72.50000000',
        'effective_at' => now()->subHour(),
        'source' => 'test',
        'created_by' => $admin->id,
        'trash' => false,
    ]);

    ExchangeRate::create([
        'from_currency_id' => $aed->id,
        'to_currency_id' => $afn->id,
        'rate' => '19.75000000',
        'effective_at' => now()->subHour(),
        'source' => 'test',
        'created_by' => $admin->id,
        'trash' => false,
    ]);

    $usd->refresh();
    expect((float) $usd->rate_to_default)->toBe(72.5);

    $history = ExchangeRateHistory::query()
        ->whereDate('rate_date', $usdRate->effective_at->toDateString())
        ->first();

    expect($history)->not->toBeNull()
        ->and((float) $history->usd_to_afn)->toBe(72.5);

    $svc = app(ExchangeService::class);
    $snap = $svc->snapshot('USD', 'AFN');
    $current = $svc->getCurrentRates();

    expect((float) $snap['rate'])->toBe(72.5)
        ->and($snap['source'])->toBe('test')
        ->and((float) $current['usd_to_afn'])->toBe(72.5)
        ->and((float) $current['aed_to_afn'])->toBe(19.75)
        ->and((float) $current['usd_to_aed'])->toBe(3.67088608);
});
