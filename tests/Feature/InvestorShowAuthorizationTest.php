<?php

use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorProfitEntry;
use App\Models\InvestorWallet;
use App\Models\User;

test('super admin can open complete investor details', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $investor = Investor::factory()->create(['full_name' => 'Detail Investor']);
    InvestorWallet::factory()->create([
        'investor_id' => $investor->id,
        'capital_balance_afn' => 500,
        'profit_balance_afn' => 40,
    ]);
    $investment = Investment::factory()->create([
        'investor_id' => $investor->id,
        'capital_amount_afn' => 500,
        'status' => 'active',
    ]);
    InvestorProfitEntry::factory()->create([
        'investor_id' => $investor->id,
        'investment_id' => $investment->id,
        'profit_amount_afn' => 25,
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->get("/investors/{$investor->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('investors/show')
            ->where('investor.id', $investor->id)
            ->where('investor.full_name', 'Detail Investor')
            ->has('investor.wallet')
            ->has('investor.investments', 1)
            ->has('investor.profit_entries', 1)
            ->has('funding.contributed_by_currency')
            ->has('funding.unallocated_by_currency')
            ->has('funded_purchases')
            ->where('stats.total_investments', 1)
        );
});

test('cashiers cannot retrieve investor details by url', function () {
    $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
    $investor = Investor::factory()->create();

    $this->actingAs($cashier)
        ->get("/investors/{$investor->id}")
        ->assertForbidden();
});

test('guests cannot retrieve investor details', function () {
    $investor = Investor::factory()->create();

    $this->get("/investors/{$investor->id}")
        ->assertRedirect('/login');
});
