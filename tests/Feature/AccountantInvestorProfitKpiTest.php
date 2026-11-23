<?php

/**
 * Priority 1 — Accountant Dashboard investor profit KPIs.
 * Runs only against zuha_testing.
 */

use App\Models\Currency;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorTransaction;
use App\Models\InvestorWalletBalance;
use App\Models\User;
use App\Services\CurrencyService;

test('accountant KPIs separate earned, paid, and outstanding investor profit', function () {
    $accountant = User::factory()->create(['role' => User::ROLE_ACCOUNTANT]);
    $this->actingAs($accountant);

    $afn = Currency::query()->where('code', 'AFN')->firstOrFail();
    $investor = Investor::factory()->create();
    $investment = Investment::factory()->create([
        'investor_id' => $investor->id,
        'currency_id' => $afn->id,
        'amount_afn' => 1000,
        'amount_currency' => 1000,
        'exchange_rate' => 1,
        'status' => 'active',
    ]);

    InvestorTransaction::factory()->create([
        'investor_id' => $investor->id,
        'investment_id' => $investment->id,
        'currency_id' => $afn->id,
        'type' => 'profit_earned',
        'amount_afn' => 500,
        'amount_currency' => 500,
        'balance_type' => 'profit',
        'transaction_date' => now()->toDateString(),
    ]);
    InvestorTransaction::factory()->create([
        'investor_id' => $investor->id,
        'investment_id' => $investment->id,
        'currency_id' => $afn->id,
        'type' => 'profit_earned',
        'amount_afn' => 200,
        'amount_currency' => 200,
        'balance_type' => 'profit',
        'transaction_date' => now()->toDateString(),
    ]);
    InvestorTransaction::factory()->create([
        'investor_id' => $investor->id,
        'investment_id' => $investment->id,
        'currency_id' => $afn->id,
        'type' => 'profit_withdrawn',
        'amount_afn' => -300,
        'amount_currency' => -300,
        'balance_type' => 'profit',
        'transaction_date' => now()->toDateString(),
    ]);

    InvestorWalletBalance::forInvestor((int) $investor->id, 'AFN')->update([
        'profit_balance_afn' => 400,
        'profit_balance_currency' => 400,
    ]);

    $money = app(CurrencyService::class);

    $this->get('/dashboard/accountant')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboards/accountant')
            ->where('kpis.earned_investor_profit', fn ($v) => (float) $v === (float) $money->reportAmount(700))
            ->where('kpis.paid_investor_profit', fn ($v) => (float) $v === (float) $money->reportAmount(300))
            ->where('kpis.pending_investor_profit', fn ($v) => (float) $v === (float) $money->reportAmount(400))
            ->where('kpis3c.earned_investor_profit.afn', fn ($v) => (float) $v === 700.0)
            ->where('kpis3c.paid_investor_profit.afn', fn ($v) => (float) $v === 300.0)
            ->where('kpis3c.pending_investor_profit.afn', fn ($v) => (float) $v === 400.0)
        );
});

test('accountant pending profit is zero when wallets are empty and no withdrawals exist', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ACCOUNTANT]));

    $this->get('/dashboard/accountant')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('kpis.earned_investor_profit', 0)
            ->where('kpis.paid_investor_profit', 0)
            ->where('kpis.pending_investor_profit', 0)
        );
});

test('pending withdrawal requests do not count as paid investor profit', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ACCOUNTANT]));

    $afn = Currency::query()->where('code', 'AFN')->firstOrFail();
    $investor = Investor::factory()->create();
    $investment = Investment::factory()->create([
        'investor_id' => $investor->id,
        'currency_id' => $afn->id,
        'amount_afn' => 500,
        'amount_currency' => 500,
        'exchange_rate' => 1,
        'status' => 'active',
    ]);

    InvestorTransaction::factory()->create([
        'investor_id' => $investor->id,
        'investment_id' => $investment->id,
        'currency_id' => $afn->id,
        'type' => 'profit_earned',
        'amount_afn' => 250,
        'amount_currency' => 250,
        'balance_type' => 'profit',
    ]);

    // Pending withdrawal request is NOT a profit_withdrawn ledger entry.
    \App\Models\InvestorWithdrawal::factory()->create([
        'investor_id' => $investor->id,
        'type' => 'profit',
        'amount_afn' => 100,
        'status' => 'pending',
        'withdrawal_date' => now()->toDateString(),
    ]);

    InvestorWalletBalance::forInvestor((int) $investor->id, 'AFN')->update([
        'profit_balance_afn' => 250,
        'profit_balance_currency' => 250,
    ]);

    $money = app(CurrencyService::class);

    $this->get('/dashboard/accountant')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('kpis.earned_investor_profit', fn ($v) => (float) $v === (float) $money->reportAmount(250))
            ->where('kpis.paid_investor_profit', 0)
            ->where('kpis.pending_investor_profit', fn ($v) => (float) $v === (float) $money->reportAmount(250))
        );
});

test('rejected withdrawals are not counted as paid', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ACCOUNTANT]));

    $afn = Currency::query()->where('code', 'AFN')->firstOrFail();
    $investor = Investor::factory()->create();
    $investment = Investment::factory()->create([
        'investor_id' => $investor->id,
        'currency_id' => $afn->id,
        'amount_afn' => 100,
        'amount_currency' => 100,
        'exchange_rate' => 1,
        'status' => 'active',
    ]);

    InvestorTransaction::factory()->create([
        'investor_id' => $investor->id,
        'investment_id' => $investment->id,
        'currency_id' => $afn->id,
        'type' => 'profit_earned',
        'amount_afn' => 100,
        'amount_currency' => 100,
        'balance_type' => 'profit',
    ]);

    \App\Models\InvestorWithdrawal::factory()->create([
        'investor_id' => $investor->id,
        'type' => 'profit',
        'amount_afn' => 40,
        'status' => 'rejected',
        'withdrawal_date' => now()->toDateString(),
    ]);

    InvestorWalletBalance::forInvestor((int) $investor->id, 'AFN')->update([
        'profit_balance_afn' => 100,
        'profit_balance_currency' => 100,
    ]);

    $money = app(CurrencyService::class);

    $this->get('/dashboard/accountant')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('kpis.paid_investor_profit', 0)
            ->where('kpis.pending_investor_profit', fn ($v) => (float) $v === (float) $money->reportAmount(100))
        );
});
