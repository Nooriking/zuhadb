<?php

use App\Models\Currency;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\CurrencyService;
use Illuminate\Support\Facades\Schema;

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/dashboard')->assertOk();
});

test('admin dashboard reports ledger totals in the configured reporting currency', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($admin);

    $customer = Customer::factory()->create(['current_due_afn' => 150]);

    $sale = Sale::factory()->create([
        'status' => 'completed',
        'payment_status' => 'paid',
        'sale_date' => now()->toDateString(),
        'total_afn' => 1000,
        'store_profit_afn' => 200,
        'remaining_due_afn' => 0,
        'customer_id' => $customer->id,
    ]);

    SalePayment::query()->create([
        'sale_id' => $sale->id,
        'customer_id' => $customer->id,
        'amount_afn' => 1000,
        'currency_code' => 'AFN',
        'amount_currency' => 1000,
        'rate_to_ledger' => 1,
        'rate_kind' => 'ledger',
        'payment_method' => 'cash',
        'paid_at' => now(),
        'received_by' => $admin->id,
    ]);

    Expense::factory()->create([
        'amount_afn' => 50,
        'expense_date' => now()->toDateString(),
    ]);

    $money = app(CurrencyService::class);

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('kpis.today_sales', fn ($value) => (float) $value === (float) $money->reportAmount(1000))
            ->where('kpis.today_collected', fn ($value) => (float) $value === (float) $money->reportAmount(1000))
            ->where('kpis.today_profit', fn ($value) => (float) $value === (float) $money->reportAmount(200))
            ->where('kpis.month_expenses', fn ($value) => (float) $value === (float) $money->reportAmount(50))
            ->where('kpis.month_net', fn ($value) => (float) $value === (float) $money->reportAmount(150))
            ->where('kpis.outstanding_due', fn ($value) => (float) $value === (float) $money->reportAmount(150))
            ->where('reporting.currency.code', fn ($code) => is_string($code) && $code !== '')
            ->has('paymentCurrencyTotals')
        );
});

test('admin dashboard converts ledger KPIs when a valid default rate exists', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    Sale::factory()->create([
        'status' => 'completed',
        'sale_date' => now()->toDateString(),
        'total_afn' => 700,
        'store_profit_afn' => 70,
        'remaining_due_afn' => 0,
    ]);

    $usd = Currency::query()->where('code', 'USD')->first()
        ?? Currency::factory()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'rate_to_default' => '70',
            'is_base' => false,
            'status' => 'active',
        ]);
    $usd->update(['rate_to_default' => '70', 'status' => 'active', 'trash' => false]);
    app(CurrencyService::class)->makeDefault($usd);

    $expected = app(CurrencyService::class)->reportAmount(700);

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('reporting.currency.code', 'USD')
            ->where('reporting.converts_from_ledger', true)
            ->where('kpis.today_sales', fn ($value) => (float) $value === (float) $expected)
        );
});

test('admin dashboard stays on ledger amounts when conversion rates are missing', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    Sale::factory()->create([
        'status' => 'completed',
        'sale_date' => now()->toDateString(),
        'total_afn' => 400,
        'store_profit_afn' => 40,
        'remaining_due_afn' => 0,
    ]);

    Currency::query()->where('code', '!=', config('business.default_currency', 'AFN'))
        ->update(['rate_to_default' => 0, 'is_base' => false]);
    Currency::query()->where('code', config('business.default_currency', 'AFN'))
        ->update(['is_base' => true, 'rate_to_default' => 1]);

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('reporting.converts_from_ledger', false)
            ->where('kpis.today_sales', fn ($value) => (float) $value === 400.0)
        );
});

test('cashier dashboard uses reporting currency for money KPIs', function () {
    $branch = \App\Models\Branch::factory()->create();
    $cashier = User::factory()->create([
        'role' => User::ROLE_CASHIER,
        'branch_id' => $branch->id,
    ]);
    $this->actingAs($cashier);

    Sale::factory()->create([
        'status' => 'completed',
        'sale_date' => now()->toDateString(),
        'total_afn' => 250,
        'store_profit_afn' => 25,
        'remaining_due_afn' => 0,
        'branch_id' => $branch->id,
    ]);

    $money = app(CurrencyService::class);

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboards/cashier')
            ->where('kpis.today_sales', fn ($value) => (float) $value === (float) $money->reportAmount(250))
            ->where('reporting.currency.code', fn ($code) => is_string($code) && $code !== '')
        );
});

test('days filter scopes chart and payment currency totals without double-counting', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($admin);

    $oldSale = Sale::factory()->create([
        'status' => 'completed',
        'sale_date' => now()->subDays(40)->toDateString(),
        'total_afn' => 5000,
        'store_profit_afn' => 500,
        'remaining_due_afn' => 0,
    ]);
    $recentSale = Sale::factory()->create([
        'status' => 'completed',
        'sale_date' => now()->toDateString(),
        'total_afn' => 300,
        'store_profit_afn' => 30,
        'remaining_due_afn' => 0,
    ]);

    if (Schema::hasColumn('sale_payments', 'currency_code')) {
        SalePayment::query()->create([
            'sale_id' => $oldSale->id,
            'amount_afn' => 5000,
            'currency_code' => 'USD',
            'amount_currency' => 70,
            'rate_to_ledger' => 71.42857143,
            'rate_kind' => 'catalog',
            'payment_method' => 'cash',
            'paid_at' => now()->subDays(40),
            'received_by' => $admin->id,
        ]);
        SalePayment::query()->create([
            'sale_id' => $recentSale->id,
            'amount_afn' => 300,
            'currency_code' => 'AFN',
            'amount_currency' => 300,
            'rate_to_ledger' => 1,
            'rate_kind' => 'ledger',
            'payment_method' => 'cash',
            'paid_at' => now(),
            'received_by' => $admin->id,
        ]);
    }

    $this->get('/dashboard?days=7')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('days', 7)
            ->where('kpis.today_sales', fn ($value) => (float) $value === (float) app(CurrencyService::class)->reportAmount(300))
            ->where('paymentCurrencyTotals', function ($totals) {
                if (! Schema::hasColumn('sale_payments', 'currency_code')) {
                    return true;
                }
                $codes = collect($totals)->pluck('code')->all();

                return in_array('AFN', $codes, true) && ! in_array('USD', $codes, true);
            })
        );
});
