<?php

use App\Models\Currency;
use App\Models\Sale;
use App\Models\User;
use App\Services\CurrencyService;

test('guests cannot open currencies', function () {
    $this->get('/currencies')->assertRedirect('/login');
});

test('cashiers cannot open currencies', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_CASHIER]))
        ->get('/currencies')
        ->assertForbidden();
});

test('admins can list and create currencies', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($user)->get('/currencies')->assertOk();
    $this->actingAs($user)->get('/currencies/create')->assertOk();

    $this->actingAs($user)->post('/currencies', [
        'code' => 'gbp',
        'name' => 'Pound Sterling',
        'symbol' => '£',
        'decimal_places' => 2,
        'rate_to_default' => '90.5',
        'is_base' => false,
        'status' => 'active',
    ])->assertRedirect('/currencies');

    $gbp = Currency::query()->where('code', 'GBP')->first();

    expect($gbp)->not->toBeNull()
        ->and($gbp->name)->toBe('Pound Sterling')
        ->and((float) $gbp->rate_to_default)->toBe(90.5)
        ->and($gbp->is_base)->toBeFalse();
});

test('currency codes must be unique three-letter codes', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    Currency::factory()->create(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€']);

    $this->actingAs($user)->post('/currencies', [
        'code' => 'EU',
        'name' => 'Bad',
        'symbol' => 'x',
        'decimal_places' => 2,
        'rate_to_default' => '1',
        'status' => 'active',
    ])->assertSessionHasErrors('code');

    $this->actingAs($user)->post('/currencies', [
        'code' => 'EUR',
        'name' => 'Euro again',
        'symbol' => '€',
        'decimal_places' => 2,
        'rate_to_default' => '1',
        'status' => 'active',
    ])->assertSessionHasErrors('code');
});

test('only one currency is the default after set-as-base', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $afn = Currency::query()->where('is_base', true)->first()
        ?? Currency::factory()->create(['code' => 'AFN', 'is_base' => true, 'rate_to_default' => '1']);

    $usd = Currency::query()->where('code', 'USD')->first()
        ?? Currency::factory()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'is_base' => false,
        ]);
    $usd->update(['rate_to_default' => '70', 'status' => 'active', 'trash' => false]);

    $this->actingAs($user)->patch("/currencies/{$usd->id}/set-as-base")->assertRedirect();

    expect($usd->fresh()->is_base)->toBeTrue()
        ->and((float) $usd->fresh()->rate_to_default)->toBe(1.0)
        ->and($afn->fresh()->is_base)->toBeFalse()
        ->and(abs((float) $afn->fresh()->rate_to_default - (1 / 70)))->toBeLessThan(0.0000001);
});

test('changing the default currency does not rewrite historical sale amounts', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    $sale = Sale::factory()->create([
        'total_afn' => 1500,
        'ledger_currency_code' => 'AFN',
    ]);

    $usd = Currency::query()->where('code', 'USD')->first()
        ?? Currency::factory()->create(['code' => 'USD', 'is_base' => false, 'status' => 'active']);
    $usd->update(['rate_to_default' => '80', 'status' => 'active', 'trash' => false]);

    app(CurrencyService::class)->makeDefault($usd);

    $sale->refresh();

    expect((float) $sale->total_afn)->toBe(1500.0)
        ->and($sale->ledger_currency_code)->toBe('AFN');
});

test('the default currency cannot be deactivated', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $default = Currency::query()->where('is_base', true)->first()
        ?? Currency::factory()->create(['code' => 'AFN', 'is_base' => true, 'status' => 'active']);

    $this->actingAs($user)->patch("/currencies/{$default->id}/toggle-status")
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($default->fresh()->status)->toBe('active');
});
