<?php

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\DB;

function saleBoardSeedCurrencies(): array
{
    $afn = Currency::query()->where('code', 'AFN')->first()
        ?? Currency::factory()->create([
            'code' => 'AFN',
            'name' => 'Afghan Afghani',
            'symbol' => '؋',
            'is_base' => true,
            'rate_to_default' => '1',
            'status' => 'active',
            'trash' => false,
        ]);

    $usd = Currency::query()->where('code', 'USD')->first()
        ?? Currency::factory()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'is_base' => false,
            'rate_to_default' => '70',
            'status' => 'active',
            'trash' => false,
        ]);

    $aed = Currency::query()->where('code', 'AED')->first()
        ?? Currency::factory()->create([
            'code' => 'AED',
            'name' => 'UAE Dirham',
            'symbol' => 'د.إ',
            'is_base' => false,
            'rate_to_default' => '19',
            'status' => 'active',
            'trash' => false,
        ]);

    foreach ([$afn, $usd, $aed] as $currency) {
        $currency->update(['status' => 'active', 'trash' => false]);
    }

    return compact('afn', 'usd', 'aed');
}

test('sales create exposes live board rates from exchange_rates', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = saleBoardSeedCurrencies();

    DB::table('exchange_rates')->delete();
    app(ExchangeRateService::class)->forget();

    ExchangeRate::factory()->create([
        'from_currency_id' => $usd->id,
        'to_currency_id' => $afn->id,
        'rate' => 71.5,
        'effective_at' => now()->subHour(),
        'source' => 'manual',
        'trash' => false,
    ]);
    ExchangeRate::factory()->create([
        'from_currency_id' => $aed->id,
        'to_currency_id' => $afn->id,
        'rate' => 19.5,
        'effective_at' => now()->subHour(),
        'source' => 'manual',
        'trash' => false,
    ]);

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)
        ->get('/sales/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/create')
            ->where('canManageRates', true)
            ->where('fxBoard.board.usd_to_afn_rate', fn ($v) => (float) $v === 71.5)
            ->where('fxBoard.board.aed_to_afn_rate', fn ($v) => (float) $v === 19.5)
        );
});

test('admin can save board rates from the sale page into exchange_rates', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = saleBoardSeedCurrencies();
    app(ExchangeRateService::class)->forget();

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)
        ->post('/sales/exchange-board', [
            'usd_to_afn' => 72.25,
            'aed_to_afn' => 20.5,
            'source' => 'sale_board',
        ])
        ->assertRedirect();

    expect(ExchangeRate::query()
        ->where('from_currency_id', $usd->id)
        ->where('to_currency_id', $afn->id)
        ->where('rate', 72.25)
        ->exists())->toBeTrue()
        ->and(ExchangeRate::query()
            ->where('from_currency_id', $aed->id)
            ->where('to_currency_id', $afn->id)
            ->where('rate', 20.5)
            ->exists())->toBeTrue();

    $board = app(ExchangeRateService::class)->flatBoard();
    expect((float) $board['usd_to_afn'])->toBe(72.25)
        ->and((float) $board['aed_to_afn'])->toBe(20.5)
        ->and(round((float) $board['usd_to_aed'], 8))->toBe(round(72.25 / 20.5, 8));
});

test('cashiers cannot post board rates from the sale page', function () {
    saleBoardSeedCurrencies();

    $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

    $this->actingAs($cashier)
        ->post('/sales/exchange-board', [
            'usd_to_afn' => 99,
            'aed_to_afn' => 25,
        ])
        ->assertForbidden();
});
