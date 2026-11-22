<?php

use App\Models\Currency;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorTransaction;
use App\Models\InvestorWalletBalance;
use App\Models\InvestorWithdrawal;
use App\Models\User;
use App\Services\ExchangeRateService;

function adminUser(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

function seedInvestorBooks(): array
{
    $afn = Currency::query()->where('code', 'AFN')->first()
        ?? Currency::factory()->create([
            'code' => 'AFN',
            'name' => 'Afghani',
            'symbol' => '؋',
            'is_base' => true,
            'rate_to_default' => '1',
            'status' => 'active',
            'trash' => false,
        ]);

    $investor = Investor::factory()->create(['full_name' => 'Amina Capital']);

    InvestorWalletBalance::forInvestor((int) $investor->id, 'AFN')->update([
        'capital_balance_afn' => 1400,
        'capital_balance_currency' => 1400,
        'profit_balance_afn' => 80,
        'profit_balance_currency' => 80,
    ]);

    Investment::factory()->create([
        'investor_id' => $investor->id,
        'currency_id' => $afn->id,
        'exchange_rate' => 1,
        'amount_currency' => 1400,
        'amount_afn' => 1400,
        'investment_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $investmentId = $investor->investments()->value('id');

    InvestorTransaction::factory()->create([
        'investor_id' => $investor->id,
        'investment_id' => $investmentId,
        'currency_id' => $afn->id,
        'type' => 'profit_earned',
        'amount_afn' => 90,
        'amount_currency' => 90,
        'balance_type' => 'profit',
        'transaction_date' => now()->subDays(2)->toDateString(),
    ]);

    InvestorTransaction::factory()->create([
        'investor_id' => $investor->id,
        'investment_id' => $investmentId,
        'currency_id' => $afn->id,
        'type' => 'profit_earned',
        'amount_afn' => 30,
        'amount_currency' => 30,
        'balance_type' => 'profit',
        'transaction_date' => now()->subDays(40)->toDateString(),
    ]);

    InvestorWithdrawal::factory()->create([
        'investor_id' => $investor->id,
        'type' => 'profit',
        'amount_afn' => 25,
        'status' => 'pending',
        'withdrawal_date' => now()->toDateString(),
    ]);

    $other = Investor::factory()->create(['full_name' => 'Other Investor']);
    InvestorWalletBalance::forInvestor((int) $other->id, 'AFN')->update([
        'capital_balance_afn' => 500,
        'capital_balance_currency' => 500,
        'profit_balance_afn' => 10,
        'profit_balance_currency' => 10,
    ]);
    Investment::factory()->create([
        'investor_id' => $other->id,
        'currency_id' => $afn->id,
        'exchange_rate' => 1,
        'amount_currency' => 500,
        'amount_afn' => 500,
        'status' => 'active',
    ]);

    return [$investor, $other, $afn];
}

test('guests cannot open the investor dashboard', function () {
    $this->get('/investors/dashboard')->assertRedirect('/login');
});

test('cashiers cannot open the investor dashboard', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_CASHIER]));

    $this->get('/investors/dashboard')->assertForbidden();
});

test('admin investor dashboard exposes new KPIs wallet capital and sale-time profit', function () {
    $this->actingAs(adminUser());
    seedInvestorBooks();

    $this->get('/investors/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('investors/dashboard')
            ->where('kpis.total_capital_afn', fn ($value) => (float) $value === 1900.0)
            ->where('kpis.total_profit_afn', fn ($value) => (float) $value === 90.0)
            ->where('kpis.total_profit_earned_actual_afn', fn ($value) => (float) $value === 120.0)
            ->where('kpis.total_invested_afn', fn ($value) => (float) $value === 1900.0)
            ->has('averageRates')
            ->has('kpis3c.total_capital')
            ->has('exchange')
        );
});

test('investor users only see their own dashboard figures', function () {
    [$owner] = seedInvestorBooks();

    $this->actingAs($owner)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('investors/dashboard')
            ->where('own', true)
            ->where('kpis.total_capital_afn', fn ($value) => (float) $value === 1400.0)
            ->where('kpis.total_investors', 1)
            ->has('averageRates')
        );
});

test('weighted average FX uses investment amounts per currency', function () {
    $this->actingAs(adminUser());

    $usd = Currency::query()->where('code', 'USD')->first()
        ?? Currency::factory()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'rate_to_default' => '70',
            'is_base' => false,
            'status' => 'active',
            'trash' => false,
        ]);

    $investor = Investor::factory()->create(['full_name' => 'FX Investor']);

    // 10 @ 65 + 5 @ 65.30 + 5 @ 65.50 → 1304 / 20 = 65.20
    Investment::factory()->create([
        'investor_id' => $investor->id,
        'currency_id' => $usd->id,
        'exchange_rate' => 65,
        'amount_currency' => 10,
        'amount_afn' => 650,
        'status' => 'active',
    ]);
    Investment::factory()->create([
        'investor_id' => $investor->id,
        'currency_id' => $usd->id,
        'exchange_rate' => 65.30,
        'amount_currency' => 5,
        'amount_afn' => 326.50,
        'status' => 'active',
    ]);
    Investment::factory()->create([
        'investor_id' => $investor->id,
        'currency_id' => $usd->id,
        'exchange_rate' => 65.50,
        'amount_currency' => 5,
        'amount_afn' => 327.50,
        'status' => 'active',
    ]);

    $rows = app(ExchangeRateService::class)->investmentWeightedAverages((int) $investor->id);
    $usdRow = collect($rows)->firstWhere('currency_code', 'USD');

    expect($usdRow)->not->toBeNull()
        ->and((float) $usdRow['total_currency'])->toBe(20.0)
        ->and((float) $usdRow['total_afn'])->toBe(1304.0)
        ->and(round((float) $usdRow['weighted_avg_rate'], 2))->toBe(65.2);

    $this->get('/investors/dashboard?investor_id='.$investor->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('investors/dashboard')
            ->where('averageRates', function ($rates) {
                $usd = collect($rates)->firstWhere('currency_code', 'USD');

                return $usd
                    && round((float) ($usd['weighted_avg_rate'] ?? 0), 2) === 65.2
                    && (float) ($usd['total_currency'] ?? 0) === 20.0;
            })
        );
});
