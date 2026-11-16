<?php

use App\Models\Currency;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\CurrencyService;
use App\Support\FxBoard;

beforeEach(function () {
    $fx = app(CurrencyService::class);
    $afn = Currency::query()->where('code', 'AFN')->firstOrFail();
    $usd = Currency::query()->where('code', 'USD')->firstOrFail();
    $aed = Currency::query()->where('code', 'AED')->firstOrFail();

    $fx->postRate($usd, $afn, '70', now()->subDay()->toDateTimeString(), null, 'test');
    $fx->postRate($aed, $afn, '19', now()->subDay()->toDateTimeString(), null, 'test');
});

test('CurrencyService threeCurrencyFromLedger uses reporting-date FxBoard', function () {
    $fx = app(CurrencyService::class);
    $tri = $fx->threeCurrencyFromLedger(700);

    expect($tri['available'])->toBeTrue()
        ->and($tri['basis'])->toBe('reporting_date_fx_board')
        ->and($tri['afn'])->toBe(700.0)
        ->and($tri['usd'])->toBe(10.0)
        ->and($tri['aed'])->toBe(36.84);

    $board = FxBoard::fromArray([
        'usd_to_afn' => (string) $tri['board']['usd_to_afn'],
        'aed_to_afn' => (string) $tri['board']['aed_to_afn'],
        'effective_at' => $tri['board']['effective_at'],
        'source' => $tri['board']['source'],
    ]);
    $eq = $board->equivalents('700', 'AFN');
    expect((float) $eq['usd'])->toBe(10.0);
});

test('updating today rates does not rewrite threeCurrency historical board when at is pinned', function () {
    $fx = app(CurrencyService::class);
    $afn = Currency::query()->where('code', 'AFN')->firstOrFail();
    $usd = Currency::query()->where('code', 'USD')->firstOrFail();
    $aed = Currency::query()->where('code', 'AED')->firstOrFail();

    $past = now()->subDays(2)->toDateTimeString();
    $fx->postRate($usd, $afn, '70', $past, null, 'test');
    $fx->postRate($aed, $afn, '19', $past, null, 'test');

    $historical = $fx->threeCurrencyFromLedger(700, $past);

    $fx->postRate($usd, $afn, '99', now()->toDateTimeString(), null, 'test');
    $fx->postRate($aed, $afn, '25', now()->toDateTimeString(), null, 'test');

    $stillHistorical = $fx->threeCurrencyFromLedger(700, $past);
    $current = $fx->threeCurrencyFromLedger(700);

    expect($stillHistorical['usd'])->toBe($historical['usd'])
        ->and($stillHistorical['aed'])->toBe($historical['aed'])
        ->and($current['usd'])->not->toBe($historical['usd']);
});

test('admin dashboard exposes kpis3c and fxBoard for AFN USD AED', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($admin);

    $customer = Customer::factory()->create(['current_due_afn' => 140]);

    $sale = Sale::factory()->create([
        'status' => 'completed',
        'payment_status' => 'paid',
        'sale_date' => now()->toDateString(),
        'total_afn' => 700,
        'store_profit_afn' => 140,
        'total_investor_profit_afn' => 70,
        'remaining_due_afn' => 0,
        'customer_id' => $customer->id,
    ]);

    SalePayment::query()->create([
        'sale_id' => $sale->id,
        'customer_id' => $customer->id,
        'amount_afn' => 700,
        'currency_code' => 'AFN',
        'amount_currency' => 700,
        'rate_to_ledger' => 1,
        'rate_kind' => 'ledger',
        'payment_method' => 'cash',
        'paid_at' => now(),
        'received_by' => $admin->id,
    ]);

    Expense::factory()->create([
        'amount_afn' => 70,
        'expense_date' => now()->toDateString(),
    ]);

    $expected = app(CurrencyService::class)->threeCurrencyFromLedger(700);

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('kpis3c.today_sales')
            ->has('fxBoard')
            ->where('kpis3c.today_sales.afn', fn ($v) => (float) $v === (float) $expected['afn'])
            ->where('kpis3c.today_sales.usd', fn ($v) => (float) $v === (float) $expected['usd'])
            ->where('kpis3c.today_sales.aed', fn ($v) => (float) $v === (float) $expected['aed'])
            ->where('kpis3c.today_sales.available', true)
            ->where('kpis3c.today_sales.basis', 'reporting_date_fx_board')
            ->where('fxBoard.available', true)
            ->has('salesTrend')
            ->has('storeAssets')
        );
});

test('cashier dashboard returns three-currency money KPIs', function () {
    $branch = \App\Models\Branch::factory()->create();
    $cashier = User::factory()->create([
        'role' => User::ROLE_CASHIER,
        'branch_id' => $branch->id,
    ]);
    $this->actingAs($cashier);

    Sale::factory()->create([
        'status' => 'completed',
        'sale_date' => now()->toDateString(),
        'total_afn' => 350,
        'store_profit_afn' => 50,
        'remaining_due_afn' => 0,
        'branch_id' => $branch->id,
    ]);

    $expected = app(CurrencyService::class)->threeCurrencyFromLedger(350);

    $this->get('/dashboard/cashier')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboards/cashier')
            ->where('kpis3c.today_sales.afn', fn ($v) => (float) $v === (float) $expected['afn'])
            ->where('kpis3c.today_sales.usd', fn ($v) => (float) $v === (float) $expected['usd'])
            ->where('kpis3c.today_sales.aed', fn ($v) => (float) $v === (float) $expected['aed'])
            ->has('fxBoard')
        );
});

test('accountant dashboard returns three-currency financial KPIs', function () {
    $accountant = User::factory()->create(['role' => User::ROLE_ACCOUNTANT]);
    $this->actingAs($accountant);

    Sale::factory()->create([
        'status' => 'completed',
        'sale_date' => now()->toDateString(),
        'total_afn' => 1000,
        'store_profit_afn' => 200,
        'total_investor_profit_afn' => 100,
        'remaining_due_afn' => 0,
    ]);

    Expense::factory()->create([
        'amount_afn' => 50,
        'expense_date' => now()->toDateString(),
    ]);

    $this->get('/dashboard/accountant')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboards/accountant')
            ->has('kpis3c.month_sales')
            ->has('kpis3c.month_expenses')
            ->has('kpis3c.month_gross_profit')
            ->has('kpis3c.inventory_value')
            ->where('kpis3c.month_sales.available', true)
            ->has('fxBoard')
        );
});

test('stock dashboard exposes batch-level inventory valuation in three currencies', function () {
    $branch = \App\Models\Branch::factory()->create();
    $keeper = User::factory()->create([
        'role' => User::ROLE_STOCK_KEEPER,
        'branch_id' => $branch->id,
    ]);
    $this->actingAs($keeper);

    ProductBatch::factory()->create([
        'branch_id' => $branch->id,
        'quantity' => 10,
        'remaining_quantity' => 10,
        'unit_cost_afn' => 70,
        'total_cost_afn' => 700,
        'investor_id' => null,
        'trash' => false,
        'received_date' => now()->toDateString(),
    ]);

    $expected = app(CurrencyService::class)->threeCurrencyFromLedger(700);

    $this->get('/dashboard/stock')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboards/stock')
            ->where('kpis3c.inventory_value.afn', fn ($v) => (float) $v === (float) $expected['afn'])
            ->where('kpis3c.inventory_value.usd', fn ($v) => (float) $v === (float) $expected['usd'])
            ->where('kpis3c.inventory_value.aed', fn ($v) => (float) $v === (float) $expected['aed'])
            ->has('valuedBatches')
            ->has('fxBoard')
        );
});

test('branch manager dashboard is scoped and includes three-currency KPIs', function () {
    $branch = \App\Models\Branch::factory()->create();
    $manager = User::factory()->create([
        'role' => User::ROLE_BRANCH_MANAGER,
        'branch_id' => $branch->id,
    ]);
    $this->actingAs($manager);

    $otherBranch = \App\Models\Branch::factory()->create();

    Sale::factory()->create([
        'status' => 'completed',
        'sale_date' => now()->toDateString(),
        'total_afn' => 210,
        'store_profit_afn' => 42,
        'remaining_due_afn' => 0,
        'branch_id' => $branch->id,
    ]);

    Sale::factory()->create([
        'status' => 'completed',
        'sale_date' => now()->toDateString(),
        'total_afn' => 9999,
        'store_profit_afn' => 1,
        'remaining_due_afn' => 0,
        'branch_id' => $otherBranch->id,
    ]);

    $expected = app(CurrencyService::class)->threeCurrencyFromLedger(210);

    $this->get('/dashboard/branch')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('kpis3c.today_sales.afn', fn ($v) => (float) $v === (float) $expected['afn'])
            ->where('kpis3c.today_sales.usd', fn ($v) => (float) $v === (float) $expected['usd'])
        );
});

test('unauthorized roles cannot open accountant dashboard', function () {
    $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
    $this->actingAs($cashier);

    $this->get('/dashboard/accountant')->assertForbidden();
});

test('investor admin dashboard exposes three-currency capital and profit KPIs', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    $investor = \App\Models\Investor::factory()->create();
    \App\Models\InvestorWallet::factory()->create([
        'investor_id' => $investor->id,
        'capital_balance_afn' => 700,
        'profit_balance_afn' => 70,
        'total_invested_afn' => 700,
        'total_profit_earned_afn' => 70,
        'total_withdrawn_afn' => 0,
    ]);
    \App\Models\Investment::factory()->create([
        'investor_id' => $investor->id,
        'capital_amount_afn' => 700,
        'available_amount_afn' => 200,
        'allocated_amount_afn' => 500,
        'source_type' => 'cash',
        'investment_date' => now()->toDateString(),
        'status' => 'partially_used',
    ]);

    $expected = app(CurrencyService::class)->threeCurrencyFromLedger(700);

    $this->get('/investors/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('investors/dashboard')
            ->has('kpis3c')
            ->has('fxBoard')
            ->where('kpis3c.total_capital.afn', fn ($v) => (float) $v === (float) $expected['afn'])
            ->where('kpis3c.total_capital.usd', fn ($v) => (float) $v === (float) $expected['usd'])
            ->where('kpis3c.total_capital.aed', fn ($v) => (float) $v === (float) $expected['aed'])
        );
});
