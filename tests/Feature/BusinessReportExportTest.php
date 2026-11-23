<?php

/**
 * Period business report PDF export — uses live ledger formulas (no mock KPIs).
 */

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\User;
use App\Services\BusinessReportService;
use Inertia\Testing\AssertableInertia as Assert;

function businessReportAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

function businessReportSale(array $overrides = []): Sale
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

test('admin can open the period export page', function () {
    $admin = businessReportAdmin();

    $this->actingAs($admin)
        ->get(route('reports.export'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/export')
            ->has('reportOptions')
            ->has('defaults')
        );
});

test('cashier cannot open period export', function () {
    $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

    $this->actingAs($cashier)
        ->get(route('reports.export'))
        ->assertForbidden();
});

test('preview returns accurate sales and expense summary for this month', function () {
    $admin = businessReportAdmin();
    $this->actingAs($admin);

    $sale = businessReportSale([
        'total_afn' => 1000,
        'total_cost_afn' => 600,
        'total_profit_afn' => 400,
        'store_profit_afn' => 300,
        'total_investor_profit_afn' => 100,
    ]);

    SalePayment::factory()->create([
        'sale_id' => $sale->id,
        'customer_id' => $sale->customer_id,
        'amount_afn' => 1000,
        'payment_method' => 'cash',
        'paid_at' => now(),
        'received_by' => $admin->id,
    ]);

    Expense::factory()->create([
        'amount_afn' => 50,
        'expense_date' => now()->toDateString(),
        'trash' => false,
        'created_by' => $admin->id,
    ]);

    $this->getJson(route('reports.export.preview', [
        'quick' => 'this_month',
        'report' => 'full',
    ]))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('summary.total_sales', 1000)
        ->assertJsonPath('summary.total_expenses', 50)
        ->assertJsonPath('summary.gross_profit', 400)
        ->assertJsonPath('summary.net_profit', 250) // store 300 - expense 50
        ->assertJsonPath('summary.investor_profit', 100);
});

test('preview treats empty period as zeros without error', function () {
    $admin = businessReportAdmin();
    $this->actingAs($admin);

    $this->getJson(route('reports.export.preview', [
        'quick' => 'custom',
        'date_from' => '2001-01-01',
        'date_to' => '2001-01-31',
        'report' => 'full',
    ]))
        ->assertOk()
        ->assertJsonPath('summary.total_sales', 0)
        ->assertJsonPath('summary.total_expenses', 0)
        ->assertJsonPath('summary.gross_profit', 0);
});

test('custom range validation rejects inverted dates', function () {
    $this->actingAs(businessReportAdmin())
        ->getJson(route('reports.export.preview', [
            'quick' => 'custom',
            'date_from' => '2026-05-10',
            'date_to' => '2026-05-01',
            'report' => 'sales',
        ]))
        ->assertStatus(422);
});

test('pdf export streams a downloadable document', function () {
    $admin = businessReportAdmin();
    $this->actingAs($admin);

    businessReportSale();

    $response = $this->get(route('reports.export.pdf', [
        'quick' => 'this_month',
        'report' => 'sales',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('pdf');
});

test('business report service net sales exclude cancelled and draft sales', function () {
    $admin = businessReportAdmin();
    $service = app(BusinessReportService::class);

    businessReportSale(['total_afn' => 500, 'status' => 'completed']);
    businessReportSale(['total_afn' => 999, 'status' => 'cancelled']);
    businessReportSale(['total_afn' => 888, 'status' => 'draft']);

    $data = $service->build([
        'quick' => 'this_month',
        'report' => 'sales',
    ], $admin);

    expect($data['sales']['net_sales'])->toBe(500.0)
        ->and($data['sales']['transaction_count'])->toBe(1);
});

test('returns section uses return_date and does not invent purchase returns', function () {
    $admin = businessReportAdmin();
    $service = app(BusinessReportService::class);

    $sale = businessReportSale(['total_afn' => 700, 'sale_amount_currency' => 1000]);
    SaleReturn::factory()->create([
        'sale_id' => $sale->id,
        'branch_id' => $sale->branch_id,
        'customer_id' => $sale->customer_id,
        'status' => SaleReturn::STATUS_COMPLETED,
        'total_afn' => 300,
        'refund_amount_afn' => 300,
        'return_date' => now()->toDateString(),
        'trash' => false,
    ]);

    $data = $service->build([
        'quick' => 'this_month',
        'report' => 'full',
    ], $admin);

    expect($data['returns']['transaction_count'])->toBe(1)
        ->and($data['returns']['refund_total'])->toBe(300.0)
        ->and($data['summary']['purchase_returns_supported'])->toBeFalse()
        ->and($data['summary']['purchase_returns'])->toBeNull();
});

test('month year selector resolves correct calendar bounds', function () {
    $admin = businessReportAdmin();
    $service = app(BusinessReportService::class);

    $period = $service->resolvePeriod([
        'quick' => 'month',
        'month' => 2,
        'year' => 2024,
    ]);

    expect($period['from']->toDateString())->toBe('2024-02-01')
        ->and($period['to']->toDateString())->toBe('2024-02-29')
        ->and($period['label'])->toBe('February 2024');
});

test('branch scoped user is forced to their branch', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'role' => User::ROLE_BRANCH_MANAGER,
        'branch_id' => $branchA->id,
    ]);

    businessReportSale(['branch_id' => $branchA->id, 'total_afn' => 200]);
    businessReportSale(['branch_id' => $branchB->id, 'total_afn' => 900]);

    $service = app(BusinessReportService::class);
    $data = $service->build([
        'quick' => 'this_month',
        'report' => 'sales',
        'branch_id' => $branchB->id, // attempt to request other branch
    ], $manager);

    expect($data['meta']['branch_id'])->toBe($branchA->id)
        ->and($data['sales']['net_sales'])->toBe(200.0);
});
