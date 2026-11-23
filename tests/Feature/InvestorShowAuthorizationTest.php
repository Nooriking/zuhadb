<?php

use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorTransaction;
use App\Models\InvestorWalletBalance;
use App\Models\User;

test('super admin can open complete investor details', function () {
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $investor = Investor::factory()->create(['full_name' => 'Detail Investor']);

    InvestorWalletBalance::forInvestor($investor->id, 'AFN')->update([
        'capital_balance_afn' => 500,
        'capital_balance_currency' => 500,
        'profit_balance_afn' => 40,
        'profit_balance_currency' => 40,
    ]);

    $investment = Investment::factory()->create([
        'investor_id' => $investor->id,
        'amount_currency' => 500,
        'amount_afn' => 500,
        'status' => 'active',
    ]);

    InvestorTransaction::create([
        'investor_id' => $investor->id,
        'investment_id' => $investment->id,
        'type' => 'profit_earned',
        'amount_currency' => 25,
        'amount_afn' => 25,
        'balance_type' => 'profit',
        'balance_before_afn' => 0,
        'balance_after_afn' => 25,
        'transaction_date' => now()->toDateString(),
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get("/investors/{$investor->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('investors/show')
            ->where('investor.id', $investor->id)
            ->where('investor.name', 'Detail Investor')
            ->has('walletByCurrency')
            ->has('investments', 1)
            ->has('transactions', 1)
            ->has('totals')
            ->has('inventory')
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
