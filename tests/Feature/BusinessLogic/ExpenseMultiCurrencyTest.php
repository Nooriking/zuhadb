<?php

/**
 * Expense multi-currency + historical FX snapshot tests.
 * Uses only zuha_testing (phpunit.xml DB_DATABASE).
 */

use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\PriceResolver;
use Illuminate\Support\Facades\Schema;

function expenseEnsureCurrencies(): array
{
    PriceResolver::clearRateCache();

    $afn = Currency::query()->where('code', 'AFN')->first()
        ?? Currency::factory()->create([
            'code' => 'AFN', 'name' => 'Afghan Afghani', 'symbol' => '؋',
            'is_base' => true, 'rate_to_default' => '1', 'status' => 'active',
        ]);
    $afn->update(['is_base' => true, 'rate_to_default' => '1.00000000', 'status' => 'active', 'trash' => false]);

    $usd = Currency::query()->where('code', 'USD')->first()
        ?? Currency::factory()->create([
            'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$',
            'is_base' => false, 'rate_to_default' => '70', 'status' => 'active',
        ]);
    $usd->update(['rate_to_default' => '70.00000000', 'status' => 'active', 'trash' => false, 'is_base' => false]);

    $aed = Currency::query()->where('code', 'AED')->first()
        ?? Currency::factory()->create([
            'code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'د.إ',
            'is_base' => false, 'rate_to_default' => '19', 'status' => 'active',
        ]);
    $aed->update(['rate_to_default' => '19.00000000', 'status' => 'active', 'trash' => false, 'is_base' => false]);

    if (Schema::hasTable('exchange_rates')) {
        ExchangeRate::query()->where('from_currency_id', $usd->id)->where('to_currency_id', $afn->id)->delete();
        ExchangeRate::query()->where('from_currency_id', $aed->id)->where('to_currency_id', $afn->id)->delete();

        ExchangeRate::factory()->create([
            'from_currency_id' => $usd->id,
            'to_currency_id' => $afn->id,
            'rate' => '70.00000000',
            'effective_at' => now()->subDay(),
            'trash' => false,
        ]);
        ExchangeRate::factory()->create([
            'from_currency_id' => $aed->id,
            'to_currency_id' => $afn->id,
            'rate' => '19.00000000',
            'effective_at' => now()->subDay(),
            'trash' => false,
        ]);
    }

    return compact('afn', 'usd', 'aed');
}

beforeEach(function () {
    expect(config('database.connections.'.config('database.default').'.database'))->toBe('zuha_testing');
});

test('create AFN expense preserves amount and rate 1', function () {
    ['afn' => $afn] = expenseEnsureCurrencies();
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);

    $expense = app(ExpenseService::class)->create([
        'branch_id' => $branch->id,
        'title' => 'Rent AFN',
        'category' => 'rent',
        'currency_id' => $afn->id,
        'amount' => 5000,
        'expense_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ], $admin->id);

    expect((float) $expense->amount_currency)->toBe(5000.0)
        ->and((float) $expense->amount_afn)->toBe(5000.0)
        ->and((float) $expense->exchange_rate)->toBe(1.0)
        ->and($expense->currency_code)->toBe('AFN');
});

test('create USD expense snapshots historical rate and AFN equivalent', function () {
    ['usd' => $usd] = expenseEnsureCurrencies();
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);

    $expense = app(ExpenseService::class)->create([
        'branch_id' => $branch->id,
        'title' => 'USD supplies',
        'category' => 'supplies',
        'currency_id' => $usd->id,
        'amount' => 100,
        'expense_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ], $admin->id);

    expect((float) $expense->amount_currency)->toBe(100.0)
        ->and($expense->currency_code)->toBe('USD')
        ->and((float) $expense->exchange_rate)->toBe(70.0)
        ->and((float) $expense->amount_afn)->toBe(7000.0);
});

test('create AED expense snapshots AFN equivalent', function () {
    ['aed' => $aed] = expenseEnsureCurrencies();
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);

    $expense = app(ExpenseService::class)->create([
        'branch_id' => $branch->id,
        'title' => 'AED transport',
        'category' => 'transport',
        'currency_id' => $aed->id,
        'amount' => 200,
        'expense_date' => now()->toDateString(),
        'payment_method' => 'bank',
    ], $admin->id);

    expect((float) $expense->amount_currency)->toBe(200.0)
        ->and($expense->currency_code)->toBe('AED')
        ->and((float) $expense->exchange_rate)->toBe(19.0)
        ->and((float) $expense->amount_afn)->toBe(3800.0);
});

test('historical expense AFN does not change when live FX rate changes', function () {
    ['afn' => $afn, 'usd' => $usd] = expenseEnsureCurrencies();
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);

    $expense = app(ExpenseService::class)->create([
        'branch_id' => $branch->id,
        'title' => 'Historic USD',
        'category' => 'other',
        'currency_id' => $usd->id,
        'amount' => 100,
        'expense_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ], $admin->id);

    expect((float) $expense->amount_afn)->toBe(7000.0);

    // Tomorrow's rate change must not rewrite the stored snapshot.
    ExchangeRate::factory()->create([
        'from_currency_id' => $usd->id,
        'to_currency_id' => $afn->id,
        'rate' => '72.00000000',
        'effective_at' => now()->addDay(),
        'trash' => false,
    ]);
    $usd->update(['rate_to_default' => '72.00000000']);
    PriceResolver::clearRateCache();

    $fresh = $expense->fresh();
    expect((float) $fresh->amount_afn)->toBe(7000.0)
        ->and((float) $fresh->exchange_rate)->toBe(70.0)
        ->and((float) $fresh->amount_currency)->toBe(100.0);
});

test('expense HTTP store accepts currency and persists snapshot', function () {
    ['usd' => $usd] = expenseEnsureCurrencies();
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);

    $this->actingAs($admin)
        ->post('/expenses', [
            'branch_id' => $branch->id,
            'title' => 'HTTP USD expense',
            'category' => 'marketing',
            'currency_id' => $usd->id,
            'amount' => 50,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'description' => 'Ads',
        ])
        ->assertRedirect();

    $expense = Expense::query()->where('title', 'HTTP USD expense')->first();
    expect($expense)->not->toBeNull()
        ->and((float) $expense->amount_currency)->toBe(50.0)
        ->and((float) $expense->amount_afn)->toBe(3500.0)
        ->and($expense->currency_code)->toBe('USD');
});

test('expense update recalculates AFN from new amount/currency without duplicating rows', function () {
    ['afn' => $afn, 'usd' => $usd] = expenseEnsureCurrencies();
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);

    $expense = app(ExpenseService::class)->create([
        'branch_id' => $branch->id,
        'title' => 'Editable',
        'category' => 'rent',
        'currency_id' => $afn->id,
        'amount' => 1000,
        'expense_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ], $admin->id);

    $beforeCount = Expense::query()->count();

    $updated = app(ExpenseService::class)->update($expense, [
        'branch_id' => $branch->id,
        'title' => 'Editable',
        'category' => 'rent',
        'currency_id' => $usd->id,
        'amount' => 20,
        'expense_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ], $admin->id);

    expect(Expense::query()->count())->toBe($beforeCount)
        ->and((float) $updated->amount_currency)->toBe(20.0)
        ->and((float) $updated->amount_afn)->toBe(1400.0)
        ->and($updated->currency_code)->toBe('USD');
});

test('voided expense is excluded from ledger aggregates', function () {
    ['afn' => $afn] = expenseEnsureCurrencies();
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);

    $expense = app(ExpenseService::class)->create([
        'branch_id' => $branch->id,
        'title' => 'To void',
        'category' => 'tax',
        'currency_id' => $afn->id,
        'amount' => 250,
        'expense_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ], $admin->id);

    app(ExpenseService::class)->void($expense, $admin->id);

    expect((float) Expense::notTrashed()->sum('amount_afn'))->toBe(0.0);

    $trashed = Expense::withoutGlobalScopes()
        ->whereKey($expense->id)
        ->where('trash', true)
        ->first();

    expect($trashed)->not->toBeNull()
        ->and((float) $trashed->amount_afn)->toBe(250.0);
});

test('invalid zero amount is rejected', function () {
    ['afn' => $afn] = expenseEnsureCurrencies();
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => $branch->id]);

    $this->actingAs($admin)
        ->post('/expenses', [
            'branch_id' => $branch->id,
            'title' => 'Bad',
            'category' => 'other',
            'currency_id' => $afn->id,
            'amount' => 0,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ])
        ->assertSessionHasErrors('amount');
});
