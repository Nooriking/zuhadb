<?php

use App\Models\Currency;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorProfitEntry;
use App\Models\InvestorWalletBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function workflowAfn(): Currency
{
    return Currency::query()->where('code', 'AFN')->first()
        ?? Currency::factory()->create([
            'code' => 'AFN',
            'is_base' => true,
            'status' => 'active',
            'rate_to_default' => 1,
        ]);
}

test('create user with investor role appears in investors directory without second identity form', function () {
    expect(Schema::hasTable('investors'))->toBeFalse();

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Zahra Investor',
            'username' => 'zahra.investor',
            'email' => 'zahra.investor@example.com',
            'phone' => '0701234567',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'role' => User::ROLE_INVESTOR,
            'status' => 'active',
        ]);

    $user = User::query()->where('email', 'zahra.investor@example.com')->firstOrFail();
    $response->assertRedirect(route('investors.show', $user->id));

    expect($user->role)->toBe(User::ROLE_INVESTOR)
        ->and(InvestorWalletBalance::query()->where('investor_id', $user->id)->exists())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('investors.index', ['search' => 'Zahra']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('investors/index')
            ->has('investors.data', 1)
            ->where('investors.data.0.id', $user->id)
            ->where('investors.data.0.email', 'zahra.investor@example.com'));

    $this->actingAs($admin)
        ->get(route('investors.create'))
        ->assertRedirect(route('users.create'));

    $this->actingAs($admin)
        ->post(route('investors.store'))
        ->assertRedirect(route('users.create'));
});

test('investors index search and status filter use user identity fields', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $active = Investor::factory()->create([
        'name' => 'Active Searchable',
        'phone' => '0709999888',
        'email' => 'active.search@example.com',
        'status' => 'active',
    ]);
    Investor::factory()->create([
        'name' => 'Inactive Hidden',
        'phone' => '0701111222',
        'email' => 'inactive.search@example.com',
        'status' => 'inactive',
    ]);

    $this->actingAs($admin)
        ->get(route('investors.index', ['search' => '0709999888', 'status' => 'active']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('investors.data', 1)
            ->where('investors.data.0.id', $active->id));
});

test('investor details opens and cash capital can be added for investor user', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $investor = Investor::factory()->create(['name' => 'Capital Owner']);
    $afn = workflowAfn();

    $this->actingAs($admin)
        ->get(route('investors.show', $investor))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('investors/show')
            ->where('investor.id', $investor->id)
            ->where('investor.full_name', 'Capital Owner'));

    $this->actingAs($admin)
        ->get(route('investments.create', ['user_id' => $investor->id]))
        ->assertRedirect(route('investors.show', $investor));

    $this->actingAs($admin)
        ->post(route('investors.capital.store', $investor), [
            'source_type' => 'cash',
            'capital_amount' => 2500,
            'currency_id' => $afn->id,
            'investment_date' => now()->toDateString(),
            'notes' => 'Initial capital',
        ])
        ->assertRedirect(route('investors.show', $investor));

    $investment = Investment::query()->where('investor_id', $investor->id)->firstOrFail();
    expect((float) $investment->capital_amount_afn)->toBe(2500.0)
        ->and((int) $investment->user_id)->toBe((int) $investor->id);

    $wallet = InvestorWallet::query()->where('investor_id', $investor->id)->firstOrFail();
    expect((float) $wallet->capital_balance_afn)->toBeGreaterThan(0);
});

test('changing role away from investor hides directory entry but keeps financial history', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $investor = Investor::factory()->create(['name' => 'Role Change Person']);
    $afn = workflowAfn();

    $this->actingAs($admin)
        ->post(route('investments.store'), [
            'user_id' => $investor->id,
            'source_type' => 'cash',
            'capital_amount' => 800,
            'currency_id' => $afn->id,
            'investment_date' => now()->toDateString(),
        ])
        ->assertRedirect();

    $investmentId = Investment::query()->where('investor_id', $investor->id)->value('id');
    expect($investmentId)->not->toBeNull();

    $this->actingAs($admin)
        ->put(route('users.update', $investor->id), [
            'name' => 'Role Change Person',
            'username' => $investor->username ?: 'role.change.person',
            'email' => $investor->email,
            'phone' => $investor->phone,
            'role' => User::ROLE_CASHIER,
            'status' => 'active',
        ])
        ->assertRedirect();

    expect(User::query()->findOrFail($investor->id)->role)->toBe(User::ROLE_CASHIER)
        ->and(Investor::query()->whereKey($investor->id)->exists())->toBeFalse()
        ->and(Investment::query()->whereKey($investmentId)->exists())->toBeTrue()
        ->and((int) Investment::query()->whereKey($investmentId)->value('investor_id'))->toBe((int) $investor->id);

    $this->actingAs($admin)
        ->get(route('investors.index', ['search' => 'Role Change']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('investors.data', 0));
});

test('users with investor financial history cannot be deleted', function () {
    $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $investor = Investor::factory()->create(['name' => 'Protected History']);
    $afn = workflowAfn();

    $this->actingAs($super)
        ->post(route('investments.store'), [
            'user_id' => $investor->id,
            'source_type' => 'cash',
            'capital_amount' => 100,
            'currency_id' => $afn->id,
            'investment_date' => now()->toDateString(),
        ])
        ->assertRedirect();

    $this->actingAs($super)
        ->delete(route('users.destroy', $investor))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(User::query()->whereKey($investor->id)->exists())->toBeTrue()
        ->and(Investment::query()->where('investor_id', $investor->id)->exists())->toBeTrue();

    expect($investor->fresh()->hasInvestorFinancialHistory())->toBeTrue();
});

test('assigning investor role to existing user surfaces them in directory', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $cashier = User::factory()->create([
        'role' => User::ROLE_CASHIER,
        'name' => 'Promoted Investor',
        'email' => 'promoted@example.com',
        'password' => Hash::make('Password1!'),
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->put(route('users.update', $cashier), [
            'name' => 'Promoted Investor',
            'username' => $cashier->username ?: 'promoted.investor',
            'email' => 'promoted@example.com',
            'phone' => $cashier->phone ?: '0705555666',
            'role' => User::ROLE_INVESTOR,
            'status' => 'active',
        ])
        ->assertRedirect(route('investors.show', $cashier->id));

    expect(Investor::query()->whereKey($cashier->id)->exists())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('investors.index', ['search' => 'Promoted']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('investors.data', 1)
            ->where('investors.data.0.id', $cashier->id));
});

test('investor self-service cannot view another investor profit entry', function () {
    $a = Investor::factory()->create(['name' => 'Self A']);
    $b = Investor::factory()->create(['name' => 'Self B']);

    $entry = InvestorProfitEntry::factory()->create([
        'investor_id' => $b->id,
        'status' => 'pending',
        'profit_amount_afn' => 40,
    ]);

    $this->actingAs($a)
        ->get(route('investor-profit-entries.show', $entry))
        ->assertForbidden();
});
