<?php

use App\Models\Currency;
use App\Models\Sale;
use App\Models\User;
use App\Services\CurrencyService;

test('guests cannot open currencies', function () {
    $this->get('/currencies')->assertRedirect('/login');
});

test('cashiers cannot open currencies', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_CASHIER]));

    $this->get('/currencies')->assertForbidden();
});

test('admins can list and create currencies', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    $this->get('/currencies')->assertOk();
    $this->get('/currencies/create')->assertOk();

    $this->post('/currencies', [
        'code' => 'usd',
        'name' => 'US Dollar',
        'symbol' => '$',
        'decimal_places' => 2,
        'rate_to_default' => '70.5',
        'is_base' => false,
        'status' => 'active',
    ])->assertRedirect('/currencies');

    $usd = Currency::query()->where('code', 'USD')->first();

    expect($usd)->not->toBeNull()
        ->and($usd->name)->toBe('US Dollar')
        ->and((float) $usd->rate_to_default)->toBe(70.5)
        ->and($usd->is_base)->toBeFalse();
});

test('currency codes must be unique three-letter codes', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    Currency::factory()->create(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€']);

    $this->post('/currencies', [
        'code' => 'EU',
        'name' => 'Bad',
        'symbol' => 'x',
        'decimal_places' => 2,
        'rate_to_default' => '1',
        'status' => 'active',
    ])->assertSessionHasErrors('code');

    $this->post('/currencies', [
        'code' => 'EUR',
        'name' => 'Euro again',
        'symbol' => '€',
        'decimal_places' => 2,
        'rate_to_default' => '1',
        'status' => 'active',
    ])->assertSessionHasErrors('code');
});

test('only one currency is the default after set-as-base', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    $afn = Currency::query()->where('is_base', true)->first()
        ?? Currency::factory()->create(['code' => 'AFN', 'is_base' => true, 'rate_to_default' => '1']);

    $usd = Currency::factory()->create([
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => '$',
        'rate_to_default' => '70',
        'is_base' => false,
    ]);

    $this->patch("/currencies/{$usd->id}/set-as-base")->assertRedirect();

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

    $usd = Currency::factory()->create([
        'code' => 'USD',
        'rate_to_default' => '80',
        'is_base' => false,
        'status' => 'active',
    ]);

    app(CurrencyService::class)->makeDefault($usd);

    $sale->refresh();

    expect((float) $sale->total_afn)->toBe(1500.0)
        ->and($sale->ledger_currency_code)->toBe('AFN');
});

test('the default currency cannot be deactivated', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    $default = Currency::query()->where('is_base', true)->first()
        ?? Currency::factory()->create(['code' => 'AFN', 'is_base' => true, 'status' => 'active']);

    $this->patch("/currencies/{$default->id}/toggle-status")
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($default->fresh()->status)->toBe('active');
});
