<?php

use App\Models\Currency;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorProfitEntry;
use App\Models\InvestorWallet;
use App\Models\InvestorWithdrawal;
use App\Models\User;
use App\Services\CurrencyService;

function adminUser(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

function seedInvestorBooks(): array
{
    $investor = Investor::factory()->create(['full_name' => 'Amina Capital']);
    InvestorWallet::factory()->create([
        'investor_id' => $investor->id,
        'capital_balance_afn' => 1400,
        'profit_balance_afn' => 80,
        'total_invested_afn' => 1400,
        'total_profit_earned_afn' => 120,
        'total_withdrawn_afn' => 40,
    ]);
    Investment::factory()->create([
        'investor_id' => $investor->id,
        'capital_amount_afn' => 1400,
        'available_amount_afn' => 400,
        'allocated_amount_afn' => 1000,
        'investment_date' => now()->toDateString(),
        'status' => 'partially_used',
    ]);
    InvestorProfitEntry::factory()->create([
        'investor_id' => $investor->id,
        'investment_id' => $investor->investments()->value('id'),
        'profit_amount_afn' => 90,
        'status' => 'pending',
        'earned_at' => now()->subDays(2),
    ]);
    InvestorProfitEntry::factory()->create([
        'investor_id' => $investor->id,
        'investment_id' => $investor->investments()->value('id'),
        'profit_amount_afn' => 30,
        'status' => 'paid',
        'earned_at' => now()->subDays(40),
    ]);
    InvestorWithdrawal::factory()->create([
        'investor_id' => $investor->id,
        'type' => 'profit',
        'amount_afn' => 25,
        'status' => 'paid',
        'withdrawal_date' => now()->toDateString(),
    ]);

    $other = Investor::factory()->create(['full_name' => 'Other Investor']);
    InvestorWallet::factory()->create([
        'investor_id' => $other->id,
        'capital_balance_afn' => 500,
        'profit_balance_afn' => 10,
        'total_invested_afn' => 500,
        'total_profit_earned_afn' => 10,
        'total_withdrawn_afn' => 0,
    ]);

    return [$investor, $other];
}

test('guests cannot open the investor dashboard', function () {
    $this->get('/investors/dashboard')->assertRedirect('/login');
});

test('cashiers cannot open the investor dashboard', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_CASHIER]));

    $this->get('/investors/dashboard')->assertForbidden();
});

test('admin investor dashboard capital matches investor wallets and respects the date range', function () {
    $this->actingAs(adminUser());
    seedInvestorBooks();

    $money = app(CurrencyService::class);
    $expectedCapital = $money->reportAmount(1900);
    $expectedPeriodProfit = $money->reportAmount(90);
    $expectedLifetimeProfit = $money->reportAmount(130);

    $this->get('/investors/dashboard?days=7')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('investors/dashboard')
            ->where('kpis.total_capital', $expectedCapital)
            ->where('kpis.total_profit', $expectedPeriodProfit)
            ->where('kpis.lifetime_profit', $expectedLifetimeProfit)
            ->where('kpis.remaining_profit', $money->reportAmount(90))
            ->where('reporting.currency.code', fn ($code) => is_string($code) && $code !== '')
        );

    $this->get('/investors')->assertOk()->assertInertia(fn ($page) => $page
        ->component('investors/index')
        ->where('stats.total_capital', fn ($value) => (float) $value === 1900.0)
        ->where('stats.total_profit_paid', fn ($value) => (float) $value === 130.0)
    );
});

test('investor users only see their own dashboard figures', function () {
    [$owner] = seedInvestorBooks();
    $user = User::factory()->create(['role' => User::ROLE_INVESTOR]);
    $owner->update(['user_id' => $user->id]);

    $money = app(CurrencyService::class);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('investors/dashboard')
            ->where('own', true)
            ->where('kpis.total_capital', $money->reportAmount(1400))
            ->where('counters.total_investors', 1)
        );
});

test('dashboard converts ledger totals when a valid default rate exists', function () {
    $this->actingAs(adminUser());
    seedInvestorBooks();

    $afn = Currency::query()->where('code', 'AFN')->first()
        ?? Currency::factory()->create(['code' => 'AFN', 'is_base' => true, 'rate_to_default' => '1']);
    $usd = Currency::factory()->create([
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => '$',
        'rate_to_default' => '70',
        'is_base' => false,
        'status' => 'active',
    ]);
    app(CurrencyService::class)->makeDefault($usd);

    $expected = app(CurrencyService::class)->reportAmount(1900);

    $this->get('/investors/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('reporting.currency.code', 'USD')
            ->where('reporting.converts_from_ledger', true)
            ->where('kpis.total_capital', $expected)
        );

    expect($expected)->toBeGreaterThan(0)->not->toBe(1900.0);
});

test('ledger amounts stay unconverted when the exchange rate is missing', function () {
    $this->actingAs(adminUser());
    seedInvestorBooks();

    $usd = Currency::factory()->create([
        'code' => 'USD',
        'rate_to_default' => '0',
        'is_base' => true,
        'status' => 'active',
    ]);
    Currency::query()->where('id', '!=', $usd->id)->update(['is_base' => false, 'rate_to_default' => '0']);

    $this->get('/investors/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('reporting.converts_from_ledger', false)
            ->where('kpis.total_capital', 1900.0)
        );
});
