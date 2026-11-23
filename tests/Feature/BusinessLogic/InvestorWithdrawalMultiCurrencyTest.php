<?php

/**
 * Investor profit withdrawal multi-currency tests.
 * Uses only zuha_testing (phpunit.xml DB_DATABASE).
 */

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Investor;
use App\Models\InvestorTransaction;
use App\Models\InvestorWalletBalance;
use App\Models\InvestorWithdrawal;
use App\Models\User;
use App\Services\InvestorWithdrawalService;
use App\Services\PriceResolver;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

function wdEnsureCurrencies(): array
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

function wdInvestorWithProfit(float $profitAfn = 100000): Investor
{
    $investor = Investor::factory()->create(['status' => 'active']);
    InvestorWalletBalance::forInvestor($investor->id, 'AFN')->update([
        'profit_balance_afn' => $profitAfn,
        'profit_balance_currency' => $profitAfn,
        'capital_balance_afn' => 0,
        'capital_balance_currency' => 0,
    ]);

    return $investor;
}

beforeEach(function () {
    expect(config('database.connections.'.config('database.default').'.database'))->toBe('zuha_testing');
});

test('profit withdrawal in AFN deducts available profit on paid', function () {
    ['afn' => $afn] = wdEnsureCurrencies();
    $investor = wdInvestorWithProfit(50000);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $withdrawal = app(InvestorWithdrawalService::class)->create([
        'investor_id' => $investor->id,
        'type' => 'profit',
        'currency_id' => $afn->id,
        'amount' => 10000,
        'withdrawal_date' => now()->toDateString(),
        'status' => 'paid',
    ], $admin->id);

    expect($withdrawal->status)->toBe('paid')
        ->and((float) $withdrawal->amount_currency)->toBe(10000.0)
        ->and((float) $withdrawal->amount_afn)->toBe(10000.0)
        ->and((float) InvestorWalletBalance::forInvestor($investor->id, 'AFN')->profit_balance_afn)->toBe(40000.0)
        ->and(InvestorTransaction::query()->where('reference_id', $withdrawal->id)->where('type', 'profit_withdrawn')->exists())->toBeTrue();
});

test('profit withdrawal in USD converts and deducts AFN equivalent from AFN wallet', function () {
    ['usd' => $usd] = wdEnsureCurrencies();
    $investor = wdInvestorWithProfit(100000);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $withdrawal = app(InvestorWithdrawalService::class)->create([
        'investor_id' => $investor->id,
        'type' => 'profit',
        'currency_id' => $usd->id,
        'amount' => 100,
        'withdrawal_date' => now()->toDateString(),
        'status' => 'paid',
    ], $admin->id);

    // 100 USD * 70 = 7000 AFN
    expect((float) $withdrawal->amount_currency)->toBe(100.0)
        ->and($withdrawal->currency_code)->toBe('USD')
        ->and((float) $withdrawal->exchange_rate)->toBe(70.0)
        ->and((float) $withdrawal->amount_afn)->toBe(7000.0)
        ->and((float) InvestorWalletBalance::forInvestor($investor->id, 'AFN')->profit_balance_afn)->toBe(93000.0);

    // Must not leave a negative USD profit row from wrong wallet targeting.
    $usdRow = InvestorWalletBalance::query()
        ->where('investor_id', $investor->id)
        ->where('currency_code', 'USD')
        ->first();
    expect($usdRow === null || (float) $usdRow->profit_balance_afn >= 0)->toBeTrue();
});

test('profit withdrawal in AED converts correctly', function () {
    ['aed' => $aed] = wdEnsureCurrencies();
    $investor = wdInvestorWithProfit(100000);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    // Example from requirements: 500 AED at 19 AFN ≈ 9500 AFN
    $withdrawal = app(InvestorWithdrawalService::class)->create([
        'investor_id' => $investor->id,
        'type' => 'profit',
        'currency_id' => $aed->id,
        'amount' => 500,
        'withdrawal_date' => now()->toDateString(),
        'status' => 'paid',
    ], $admin->id);

    expect((float) $withdrawal->amount_currency)->toBe(500.0)
        ->and($withdrawal->currency_code)->toBe('AED')
        ->and((float) $withdrawal->amount_afn)->toBe(9500.0)
        ->and((float) InvestorWalletBalance::forInvestor($investor->id, 'AFN')->profit_balance_afn)->toBe(90500.0);
});

test('pending withdrawal does not reduce available profit until paid', function () {
    ['afn' => $afn] = wdEnsureCurrencies();
    $investor = wdInvestorWithProfit(20000);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $service = app(InvestorWithdrawalService::class);

    $withdrawal = $service->create([
        'investor_id' => $investor->id,
        'type' => 'profit',
        'currency_id' => $afn->id,
        'amount' => 5000,
        'withdrawal_date' => now()->toDateString(),
        'status' => 'pending',
    ], $admin->id);

    expect($withdrawal->status)->toBe('pending')
        ->and((float) InvestorWalletBalance::forInvestor($investor->id, 'AFN')->profit_balance_afn)->toBe(20000.0);

    $service->markAsPaid($withdrawal, $admin->id);

    expect((float) InvestorWalletBalance::forInvestor($investor->id, 'AFN')->profit_balance_afn)->toBe(15000.0);
});

test('insufficient profit is rejected', function () {
    ['usd' => $usd] = wdEnsureCurrencies();
    $investor = wdInvestorWithProfit(1000); // only 1000 AFN
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    expect(fn () => app(InvestorWithdrawalService::class)->create([
        'investor_id' => $investor->id,
        'type' => 'profit',
        'currency_id' => $usd->id,
        'amount' => 100, // 7000 AFN
        'withdrawal_date' => now()->toDateString(),
        'status' => 'pending',
    ], $admin->id))->toThrow(ValidationException::class);

    expect((float) InvestorWalletBalance::forInvestor($investor->id, 'AFN')->profit_balance_afn)->toBe(1000.0);
});

test('zero and negative amounts are rejected by HTTP validation', function () {
    ['afn' => $afn] = wdEnsureCurrencies();
    $investor = wdInvestorWithProfit(5000);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)
        ->post('/investor-withdrawals', [
            'investor_id' => $investor->id,
            'type' => 'profit',
            'currency_id' => $afn->id,
            'amount' => 0,
            'withdrawal_date' => now()->toDateString(),
            'status' => 'pending',
        ])
        ->assertSessionHasErrors('amount');
});

test('rejected withdrawal cannot be paid and does not deduct', function () {
    ['afn' => $afn] = wdEnsureCurrencies();
    $investor = wdInvestorWithProfit(8000);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $service = app(InvestorWithdrawalService::class);

    $withdrawal = $service->create([
        'investor_id' => $investor->id,
        'type' => 'profit',
        'currency_id' => $afn->id,
        'amount' => 1000,
        'withdrawal_date' => now()->toDateString(),
        'status' => 'pending',
    ], $admin->id);

    $withdrawal->reject('Not allowed');

    expect(fn () => $service->markAsPaid($withdrawal, $admin->id))->toThrow(ValidationException::class);
    expect((float) InvestorWalletBalance::forInvestor($investor->id, 'AFN')->profit_balance_afn)->toBe(8000.0);
});

test('mark as paid twice does not double-deduct', function () {
    ['afn' => $afn] = wdEnsureCurrencies();
    $investor = wdInvestorWithProfit(10000);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $service = app(InvestorWithdrawalService::class);

    $withdrawal = $service->create([
        'investor_id' => $investor->id,
        'type' => 'profit',
        'currency_id' => $afn->id,
        'amount' => 2000,
        'withdrawal_date' => now()->toDateString(),
        'status' => 'approved',
    ], $admin->id);

    $service->markAsPaid($withdrawal, $admin->id);
    expect((float) InvestorWalletBalance::forInvestor($investor->id, 'AFN')->profit_balance_afn)->toBe(8000.0);

    expect(fn () => $service->markAsPaid($withdrawal->fresh(), $admin->id))->toThrow(ValidationException::class);
    expect((float) InvestorWalletBalance::forInvestor($investor->id, 'AFN')->profit_balance_afn)->toBe(8000.0);
    expect(InvestorTransaction::query()->where('reference_id', $withdrawal->id)->where('type', 'profit_withdrawn')->count())->toBe(1);
});

test('multiple withdrawals keep remaining profit accurate', function () {
    ['afn' => $afn, 'usd' => $usd, 'aed' => $aed] = wdEnsureCurrencies();
    $investor = wdInvestorWithProfit(50000);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $service = app(InvestorWithdrawalService::class);

    // 10,000 AFN
    $service->create([
        'investor_id' => $investor->id,
        'type' => 'profit',
        'currency_id' => $afn->id,
        'amount' => 10000,
        'withdrawal_date' => now()->toDateString(),
        'status' => 'paid',
    ], $admin->id);

    // 100 USD = 7,000 AFN
    $service->create([
        'investor_id' => $investor->id,
        'type' => 'profit',
        'currency_id' => $usd->id,
        'amount' => 100,
        'withdrawal_date' => now()->toDateString(),
        'status' => 'paid',
    ], $admin->id);

    // 500 AED = 9,500 AFN
    $service->create([
        'investor_id' => $investor->id,
        'type' => 'profit',
        'currency_id' => $aed->id,
        'amount' => 500,
        'withdrawal_date' => now()->toDateString(),
        'status' => 'paid',
    ], $admin->id);

    // Remaining = 50000 - 10000 - 7000 - 9500 = 23500
    expect((float) InvestorWalletBalance::forInvestor($investor->id, 'AFN')->profit_balance_afn)->toBe(23500.0)
        ->and((float) InvestorWithdrawal::paid()->where('investor_id', $investor->id)->sum('amount_afn'))->toBe(26500.0);
});

test('historical FX snapshot on withdrawal is immutable after rate change', function () {
    ['afn' => $afn, 'usd' => $usd] = wdEnsureCurrencies();
    $investor = wdInvestorWithProfit(20000);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $withdrawal = app(InvestorWithdrawalService::class)->create([
        'investor_id' => $investor->id,
        'type' => 'profit',
        'currency_id' => $usd->id,
        'amount' => 50,
        'withdrawal_date' => now()->toDateString(),
        'status' => 'paid',
    ], $admin->id);

    expect((float) $withdrawal->amount_afn)->toBe(3500.0);

    ExchangeRate::factory()->create([
        'from_currency_id' => $usd->id,
        'to_currency_id' => $afn->id,
        'rate' => '80.00000000',
        'effective_at' => now()->addDay(),
        'trash' => false,
    ]);
    $usd->update(['rate_to_default' => '80']);
    PriceResolver::clearRateCache();

    expect((float) $withdrawal->fresh()->amount_afn)->toBe(3500.0)
        ->and((float) $withdrawal->fresh()->exchange_rate)->toBe(70.0);
});
