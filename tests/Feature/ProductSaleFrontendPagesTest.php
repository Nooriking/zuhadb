<?php

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\DB;

function frontendPageSeedBoard(): array
{
    $afn = Currency::query()->where('code', 'AFN')->first()
        ?? Currency::factory()->create([
            'code' => 'AFN',
            'name' => 'Afghan Afghani',
            'symbol' => '؋',
            'is_base' => false,
            'rate_to_default' => '0.01538461',
            'status' => 'active',
            'trash' => false,
        ]);

    $usd = Currency::query()->where('code', 'USD')->first()
        ?? Currency::factory()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'is_base' => true,
            'rate_to_default' => '1',
            'status' => 'active',
            'trash' => false,
        ]);

    $aed = Currency::query()->where('code', 'AED')->first()
        ?? Currency::factory()->create([
            'code' => 'AED',
            'name' => 'UAE Dirham',
            'symbol' => 'د.إ',
            'is_base' => false,
            'rate_to_default' => '0.69230769',
            'status' => 'active',
            'trash' => false,
        ]);

    // Mirror production quirk: USD may be catalog is_base while ledger is AFN.
    $afn->update(['is_base' => false, 'status' => 'active', 'trash' => false, 'rate_to_default' => '0.01538461']);
    $usd->update(['is_base' => true, 'status' => 'active', 'trash' => false, 'rate_to_default' => '1']);
    $aed->update(['is_base' => false, 'status' => 'active', 'trash' => false, 'rate_to_default' => '0.69230769']);

    DB::table('exchange_rates')->delete();
    app(ExchangeRateService::class)->forget();

    ExchangeRate::factory()->create([
        'from_currency_id' => $usd->id,
        'to_currency_id' => $afn->id,
        'rate' => 65,
        'effective_at' => now()->subHour(),
        'source' => 'manual',
        'trash' => false,
    ]);
    ExchangeRate::factory()->create([
        'from_currency_id' => $aed->id,
        'to_currency_id' => $afn->id,
        'rate' => 45,
        'effective_at' => now()->subHour(),
        'source' => 'manual',
        'trash' => false,
    ]);

    return compact('afn', 'usd', 'aed');
}

test('products create page exposes currencies and fx board for frontend preview', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = frontendPageSeedBoard();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)
        ->get('/products/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('products/create')
            ->has('currencies')
            ->where('currencies', function ($rows) {
                $codes = collect($rows)->pluck('code')->map(fn ($c) => strtoupper((string) $c))->all();

                return in_array('USD', $codes, true)
                    && in_array('AFN', $codes, true)
                    && in_array('AED', $codes, true);
            })
            ->where('fxBoard.board.usd_to_afn_rate', fn ($v) => (float) $v === 65.0)
            ->where('fxBoard.board.aed_to_afn_rate', fn ($v) => (float) $v === 45.0)
            ->etc()
        );

    expect($usd->fresh()->code)->toBe('USD')
        ->and($afn->fresh()->code)->toBe('AFN')
        ->and($aed->fresh()->code)->toBe('AED');
});

test('sales create page exposes live fx board for frontend sale pricing', function () {
    frontendPageSeedBoard();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)
        ->get('/sales/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/create')
            ->where('fxBoard.board.usd_to_afn_rate', fn ($v) => (float) $v === 65.0)
            ->where('fxBoard.board.aed_to_afn_rate', fn ($v) => (float) $v === 45.0)
            ->etc()
        );
});
