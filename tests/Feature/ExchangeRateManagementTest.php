<?php

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;

function seedPairCurrencies(): array
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

    $afn->update(['status' => 'active', 'trash' => false]);
    $usd->update(['status' => 'active', 'trash' => false]);

    return [$afn, $usd];
}

test('guests cannot open exchange rates', function () {
    $this->get('/exchange-rates')->assertRedirect('/login');
});

test('cashiers cannot open exchange rates', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_CASHIER]))
        ->get('/exchange-rates')
        ->assertForbidden();
});

test('admins can open exchange rate pages', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    seedPairCurrencies();

    $this->actingAs($user)->get('/exchange-rates')->assertOk();
    $this->actingAs($user)->get('/exchange-rates/create')->assertOk();
    $this->actingAs($user)->get('/exchange-rates/history')->assertOk();
    $this->actingAs($user)->get('/exchange-rates/trash')->assertOk();
});

test('admins can create an exchange rate', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    [$afn, $usd] = seedPairCurrencies();

    $this->actingAs($user)->post('/exchange-rates', [
        'from_currency_id' => $usd->id,
        'to_currency_id' => $afn->id,
        'rate' => '71.25',
        'effective_at' => now()->toDateTimeString(),
        'source' => 'market',
    ])->assertRedirect('/exchange-rates');

    $rate = ExchangeRate::query()
        ->where('from_currency_id', $usd->id)
        ->where('to_currency_id', $afn->id)
        ->latest('id')
        ->first();

    expect($rate)->not->toBeNull()
        ->and((float) $rate->rate)->toBe(71.25)
        ->and($rate->source)->toBe('market')
        ->and($rate->trash)->toBeFalse();
});

test('posting AFN to USD is normalized to USD to AFN for the board', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    [$afn, $usd] = seedPairCurrencies();

    $this->actingAs($user)->post('/exchange-rates', [
        'from_currency_id' => $afn->id,
        'to_currency_id' => $usd->id,
        'rate' => '65',
        'effective_at' => now()->toDateTimeString(),
        'source' => 'manual',
    ])->assertRedirect('/exchange-rates');

    expect(ExchangeRate::query()
        ->where('from_currency_id', $usd->id)
        ->where('to_currency_id', $afn->id)
        ->where('rate', 65)
        ->exists())->toBeTrue()
        ->and(ExchangeRate::query()
            ->notTrashed()
            ->where('from_currency_id', $afn->id)
            ->where('to_currency_id', $usd->id)
            ->exists())->toBeFalse();
});

test('exchange rate validation rejects same currencies and non-positive rates', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    [$afn] = seedPairCurrencies();

    $this->actingAs($user)->post('/exchange-rates', [
        'from_currency_id' => $afn->id,
        'to_currency_id' => $afn->id,
        'rate' => '1',
        'effective_at' => now()->toDateTimeString(),
    ])->assertSessionHasErrors('from_currency_id');

    $this->actingAs($user)->post('/exchange-rates', [
        'from_currency_id' => $afn->id,
        'to_currency_id' => Currency::factory()->create(['code' => 'EUR', 'status' => 'active'])->id,
        'rate' => '0',
        'effective_at' => now()->toDateTimeString(),
    ])->assertSessionHasErrors('rate');
});

test('exchange rates can be soft deleted restored and force deleted', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    [$afn, $usd] = seedPairCurrencies();

    $rate = ExchangeRate::factory()->create([
        'from_currency_id' => $usd->id,
        'to_currency_id' => $afn->id,
        'rate' => 70,
        'effective_at' => now()->subHour(),
        'source' => 'manual',
        'trash' => false,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->delete("/exchange-rates/{$rate->id}")
        ->assertRedirect('/exchange-rates');

    expect($rate->fresh()->trash)->toBeTrue();

    $this->actingAs($user)
        ->get('/exchange-rates/trash')
        ->assertOk();

    $this->actingAs($user)
        ->post("/exchange-rates/{$rate->id}/restore")
        ->assertRedirect('/exchange-rates');

    expect($rate->fresh()->trash)->toBeFalse();

    $this->actingAs($user)->delete("/exchange-rates/{$rate->id}");
    $this->actingAs($user)
        ->delete("/exchange-rates/{$rate->id}/force-delete")
        ->assertRedirect('/exchange-rates/trash');

    expect(ExchangeRate::query()->find($rate->id))->toBeNull()
        ->and(ExchangeRate::onlyTrashed()->find($rate->id))->toBeNull();
});

test('admins can view and update an exchange rate', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    [$afn, $usd] = seedPairCurrencies();

    $rate = ExchangeRate::factory()->create([
        'from_currency_id' => $usd->id,
        'to_currency_id' => $afn->id,
        'rate' => 70,
        'effective_at' => now()->subDay(),
        'source' => 'manual',
        'trash' => false,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)->get("/exchange-rates/{$rate->id}")->assertOk();
    $this->actingAs($user)->get("/exchange-rates/{$rate->id}/edit")->assertOk();

    $this->actingAs($user)->put("/exchange-rates/{$rate->id}", [
        'from_currency_id' => $usd->id,
        'to_currency_id' => $afn->id,
        'rate' => '72.5',
        'effective_at' => now()->toDateTimeString(),
        'source' => 'bank',
    ])->assertRedirect('/exchange-rates');

    expect((float) $rate->fresh()->rate)->toBe(72.5)
        ->and($rate->fresh()->source)->toBe('bank');
});
