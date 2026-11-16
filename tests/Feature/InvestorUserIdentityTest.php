<?php

use App\Models\Currency;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorProfitEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function identityAfnCurrency(): Currency
{
    return Currency::query()->where('code', 'AFN')->first()
        ?? Currency::factory()->create([
            'code' => 'AFN',
            'is_base' => true,
            'status' => 'active',
            'rate_to_default' => 1,
        ]);
}

test('investor identity is stored only on users table', function () {
    expect(Schema::hasTable('investors'))->toBeFalse();

    $investor = Investor::factory()->create([
        'full_name' => 'Ahmad Khan',
        'phone' => '0700111222',
        'email' => 'ahmad.investor@example.com',
    ]);

    expect($investor->id)->toBeInt()
        ->and($investor->role)->toBe(User::ROLE_INVESTOR)
        ->and($investor->full_name)->toBe('Ahmad Khan')
        ->and($investor->name)->toBe('Ahmad Khan')
        ->and(User::query()->whereKey($investor->id)->exists())->toBeTrue();
});

test('admin can create multiple investments for the same investor user', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $investor = Investor::factory()->create(['full_name' => 'Mohammad Ahmad']);
    $afn = identityAfnCurrency();

    $this->actingAs($admin)
        ->post(route('investments.store'), [
            'user_id' => $investor->id,
            'source_type' => 'cash',
            'capital_amount' => 1000,
            'currency_id' => $afn->id,
            'investment_date' => now()->toDateString(),
            'notes' => 'First cash',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('investments.store'), [
            'user_id' => $investor->id,
            'source_type' => 'cash',
            'capital_amount' => 500,
            'currency_id' => $afn->id,
            'investment_date' => now()->toDateString(),
            'notes' => 'Second cash',
        ])
        ->assertRedirect();

    $investments = Investment::query()->where('investor_id', $investor->id)->orderBy('id')->get();

    expect($investments)->toHaveCount(2)
        ->and($investments->every(fn ($row) => (int) $row->investor_id === (int) $investor->id))->toBeTrue()
        ->and($investments->every(fn ($row) => (int) $row->user_id === (int) $investor->id))->toBeTrue();
});

test('investment selector rejects non-investor users', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
    $afn = identityAfnCurrency();

    $this->actingAs($admin)
        ->post(route('investments.store'), [
            'user_id' => $cashier->id,
            'source_type' => 'cash',
            'capital_amount' => 100,
            'currency_id' => $afn->id,
            'investment_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('user_id');
});

test('investor overview lists users with investor role only', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $investor = Investor::factory()->create(['full_name' => 'Abdul Rahman']);
    User::factory()->create(['role' => User::ROLE_CASHIER, 'name' => 'Not Investor']);

    $this->actingAs($admin)
        ->get(route('investors.index', ['search' => 'Abdul']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('investors/index')
            ->has('investors.data', 1)
            ->where('investors.data.0.id', $investor->id));
});

test('investor cannot access another investor profit entry', function () {
    $a = Investor::factory()->create(['full_name' => 'Investor A']);
    $b = Investor::factory()->create(['full_name' => 'Investor B']);

    $entry = InvestorProfitEntry::factory()->create([
        'investor_id' => $b->id,
        'status' => 'pending',
        'profit_amount_afn' => 50,
    ]);

    $this->actingAs($a)
        ->get(route('investor-profit-entries.show', $entry))
        ->assertForbidden();
});

test('deactivated investor user cannot be selected for new investment', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $investor = Investor::factory()->create(['status' => 'inactive']);
    $afn = identityAfnCurrency();

    $this->actingAs($admin)
        ->post(route('investments.store'), [
            'user_id' => $investor->id,
            'source_type' => 'cash',
            'capital_amount' => 100,
            'currency_id' => $afn->id,
            'investment_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('user_id');
});
