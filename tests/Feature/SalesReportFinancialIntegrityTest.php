<?php

/**
 * Priority 1 — Sales Report financial integrity.
 * Runs only against zuha_testing (phpunit.xml / TestCase guard).
 */

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\User;

function salesReportAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

function makeCompletedSale(array $overrides = []): Sale
{
    return Sale::factory()->create(array_merge([
        'status' => 'completed',
        'payment_status' => 'paid',
        'sale_date' => now()->toDateString(),
        'sale_currency_code' => 'AFN',
        'sale_currency_symbol' => '؋',
        'sale_amount_currency' => 1000,
        'total_afn' => 1000,
        'subtotal_afn' => 1000,
        'discount_afn' => 0,
        'paid_afn' => 1000,
        'remaining_due_afn' => 0,
        'due_afn' => 0,
        'total_cost_afn' => 600,
        'total_profit_afn' => 400,
        'store_profit_afn' => 300,
        'total_investor_profit_afn' => 100,
        'trash' => false,
    ], $overrides));
}

function makeCompletedReturn(Sale $sale, float $amountAfn, array $overrides = []): SaleReturn
{
    return SaleReturn::factory()->create(array_merge([
        'sale_id' => $sale->id,
        'branch_id' => $sale->branch_id,
        'customer_id' => $sale->customer_id,
        'status' => SaleReturn::STATUS_COMPLETED,
        'total_afn' => $amountAfn,
        'subtotal_afn' => $amountAfn,
        'return_date' => now()->toDateString(),
        'trash' => false,
    ], $overrides));
}

test('sales report without returns: gross equals net and profit is real', function () {
    $admin = salesReportAdmin();
    $this->actingAs($admin);

    $sale = makeCompletedSale([
        'total_afn' => 1000,
        'sale_amount_currency' => 1000,
        'total_profit_afn' => 400,
        'store_profit_afn' => 300,
        'total_investor_profit_afn' => 100,
        'total_cost_afn' => 600,
    ]);

    SalePayment::factory()->create([
        'sale_id' => $sale->id,
        'customer_id' => $sale->customer_id,
        'amount_afn' => 1000,
        'received_by' => $admin->id,
    ]);

    $this->get('/sales/report')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/report')
            ->where('summary.gross_sales', 1000)
            ->where('summary.total_returns', 0)
            ->where('summary.net_sales', 1000)
            ->where('summary.total_paid', 1000)
            ->where('summary.total_due', 0)
            ->where('summary.total_cost', 600)
            ->where('summary.total_profit', 400)
            ->where('summary.store_profit', 300)
            ->where('summary.investor_profit', 100)
            ->where('summary.avg_sale', 1000)
            ->where('canViewProfit', true)
            ->where('byDay.0.total_profit', 400)
        );
});

test('sales report with partial return reconstructs gross without double-counting', function () {
    $this->actingAs(salesReportAdmin());

    // Sale totals already adjusted by finalizeReturn: net 700 after 300 return.
    $sale = makeCompletedSale([
        'total_afn' => 700,
        'sale_amount_currency' => 1000,
        'paid_afn' => 1000,
        'remaining_due_afn' => 0,
        'total_cost_afn' => 420,
        'total_profit_afn' => 280,
        'store_profit_afn' => 210,
        'total_investor_profit_afn' => 70,
    ]);
    makeCompletedReturn($sale, 300);

    SalePayment::factory()->create([
        'sale_id' => $sale->id,
        'customer_id' => $sale->customer_id,
        'amount_afn' => 1000,
    ]);

    $this->get('/sales/report')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.net_sales', 700)
            ->where('summary.total_returns', 300)
            ->where('summary.gross_sales', 1000)
            ->where('summary.total_paid', 1000)
            ->where('summary.total_profit', 280)
            ->where('sales.data.0.amount_afn', 1000)
            ->where('sales.data.0.net_afn', 700)
            ->where('sales.data.0.return_amount_afn', 300)
            ->where('sales.data.0.amount_original', 1000)
            ->where('sales.data.0.net_original', 700)
        );
});

test('sales report with full return shows zero net and full return', function () {
    $this->actingAs(salesReportAdmin());

    $sale = makeCompletedSale([
        'total_afn' => 0,
        'sale_amount_currency' => 1000,
        'paid_afn' => 1000,
        'total_cost_afn' => 0,
        'total_profit_afn' => 0,
        'store_profit_afn' => 0,
        'total_investor_profit_afn' => 0,
    ]);
    makeCompletedReturn($sale, 1000);

    $this->get('/sales/report')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.net_sales', 0)
            ->where('summary.total_returns', 1000)
            ->where('summary.gross_sales', 1000)
            ->where('summary.total_profit', 0)
        );
});

test('multiple returns on one sale sum correctly without double-counting currency gross', function () {
    $this->actingAs(salesReportAdmin());

    $sale = makeCompletedSale([
        'total_afn' => 400,
        'sale_amount_currency' => 1000,
        'sale_currency_code' => 'AFN',
        'total_profit_afn' => 160,
        'store_profit_afn' => 120,
        'total_investor_profit_afn' => 40,
        'total_cost_afn' => 240,
    ]);
    makeCompletedReturn($sale, 250);
    makeCompletedReturn($sale, 350);

    $this->get('/sales/report')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.net_sales', 400)
            ->where('summary.total_returns', 600)
            ->where('summary.gross_sales', 1000)
            // Currency gross must equal original sale amount, NOT original + returns.
            ->where('summary.gross_sales_by_currency.0.total', fn ($v) => (float) $v === 1000.0)
        );
});

test('salesperson filter excludes unrelated returns', function () {
    $admin = salesReportAdmin();
    $this->actingAs($admin);
    $spA = User::factory()->create(['role' => User::ROLE_SALESPERSON]);
    $spB = User::factory()->create(['role' => User::ROLE_SALESPERSON]);

    $saleA = makeCompletedSale([
        'created_by' => $spA->id,
        'total_afn' => 800,
        'sale_amount_currency' => 1000,
        'total_profit_afn' => 200,
    ]);
    makeCompletedReturn($saleA, 200);

    $saleB = makeCompletedSale([
        'created_by' => $spB->id,
        'total_afn' => 500,
        'sale_amount_currency' => 500,
        'total_profit_afn' => 100,
    ]);
    makeCompletedReturn($saleB, 999);

    $this->get('/sales/report?salesperson_id='.$spA->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.net_sales', 800)
            ->where('summary.total_returns', 200)
            ->where('summary.gross_sales', 1000)
            ->where('summary.count', 1)
        );
});

test('branch filter scopes sales and returns together', function () {
    $this->actingAs(salesReportAdmin());
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $saleA = makeCompletedSale([
        'branch_id' => $branchA->id,
        'total_afn' => 700,
        'sale_amount_currency' => 900,
    ]);
    makeCompletedReturn($saleA, 200, ['branch_id' => $branchA->id]);

    $saleB = makeCompletedSale([
        'branch_id' => $branchB->id,
        'total_afn' => 50,
        'sale_amount_currency' => 50,
    ]);
    makeCompletedReturn($saleB, 50, ['branch_id' => $branchB->id]);

    $this->get('/sales/report?branch_id='.$branchA->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.net_sales', 700)
            ->where('summary.total_returns', 200)
            ->where('summary.gross_sales', 900)
        );
});

test('customer filter and due-only filter work with payment status', function () {
    $this->actingAs(salesReportAdmin());
    $customer = Customer::factory()->create();

    $dueSale = makeCompletedSale([
        'customer_id' => $customer->id,
        'total_afn' => 500,
        'sale_amount_currency' => 500,
        'paid_afn' => 200,
        'remaining_due_afn' => 300,
        'payment_status' => 'partial',
    ]);
    SalePayment::factory()->create([
        'sale_id' => $dueSale->id,
        'customer_id' => $customer->id,
        'amount_afn' => 200,
    ]);

    makeCompletedSale([
        'customer_id' => $customer->id,
        'total_afn' => 100,
        'sale_amount_currency' => 100,
        'paid_afn' => 100,
        'remaining_due_afn' => 0,
        'payment_status' => 'paid',
    ]);

    makeCompletedSale([
        'total_afn' => 9999,
        'sale_amount_currency' => 9999,
        'payment_status' => 'unpaid',
        'paid_afn' => 0,
        'remaining_due_afn' => 9999,
    ]);

    $this->get('/sales/report?customer_id='.$customer->id.'&only_due=1&payment_status=partial')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.count', 1)
            ->where('summary.net_sales', 500)
            ->where('summary.total_paid', 200)
            ->where('summary.total_due', 300)
        );
});

test('date filter attributes returns to parent sale date not return_date alone', function () {
    $this->actingAs(salesReportAdmin());

    $sale = makeCompletedSale([
        'sale_date' => '2026-01-10',
        'total_afn' => 0,
        'sale_amount_currency' => 1000,
        'total_profit_afn' => 0,
        'store_profit_afn' => 0,
        'total_investor_profit_afn' => 0,
        'total_cost_afn' => 0,
    ]);
    // Return happened later — still counted with the filtered sale.
    makeCompletedReturn($sale, 1000, ['return_date' => '2026-02-05']);

    $this->get('/sales/report?date_from=2026-01-01&date_to=2026-01-31')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.net_sales', 0)
            ->where('summary.total_returns', 1000)
            ->where('summary.gross_sales', 1000)
            ->where('byDay.0.total_profit', 0)
            ->where('byDay.0.total_returns', 1000)
        );

    // February-only window must not pick up January sale/return reconstruction.
    $this->get('/sales/report?date_from=2026-02-01&date_to=2026-02-28')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.net_sales', 0)
            ->where('summary.total_returns', 0)
            ->where('summary.gross_sales', 0)
            ->where('summary.count', 0)
        );
});

test('multi-currency sale uses historical original amount for gross buckets', function () {
    $this->actingAs(salesReportAdmin());

    $sale = makeCompletedSale([
        'sale_currency_code' => 'USD',
        'sale_currency_symbol' => '$',
        'sale_amount_currency' => 20,
        'usd_to_afn_rate' => 70,
        'total_afn' => 700, // after partial return of 700 AFN / $10
        'total_profit_afn' => 200,
        'store_profit_afn' => 150,
        'total_investor_profit_afn' => 50,
        'total_cost_afn' => 500,
    ]);
    makeCompletedReturn($sale, 700, [
        'return_amount_currency' => 10,
        'return_currency_code' => 'USD',
        'return_currency_symbol' => '$',
    ]);

    $this->get('/sales/report')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.net_sales', 700)
            ->where('summary.total_returns', 700)
            ->where('summary.gross_sales', 1400)
            ->where('summary.gross_sales_by_currency', function ($rows) {
                $usd = collect($rows)->firstWhere('code', 'USD');

                return $usd !== null && (float) $usd['total'] === 20.0;
            })
            ->where('summary.total_returns_by_currency', function ($rows) {
                $usd = collect($rows)->firstWhere('code', 'USD');

                return $usd !== null && (float) $usd['total'] === 10.0;
            })
            ->where('summary.net_sales_by_currency', function ($rows) {
                $usd = collect($rows)->firstWhere('code', 'USD');

                return $usd !== null && (float) $usd['total'] === 10.0;
            })
        );
});

test('cashier cannot see store or investor profit on sales report', function () {
    $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
    $this->actingAs($cashier);

    makeCompletedSale([
        'total_afn' => 1000,
        'store_profit_afn' => 300,
        'total_investor_profit_afn' => 100,
        'total_profit_afn' => 400,
    ]);

    $this->get('/sales/report')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canViewProfit', false)
            ->where('summary.store_profit', null)
            ->where('summary.investor_profit', null)
            ->where('summary.total_profit', null)
            ->where('summary.total_cost', null)
            ->where('byDay.0.total_profit', null)
        );
});
