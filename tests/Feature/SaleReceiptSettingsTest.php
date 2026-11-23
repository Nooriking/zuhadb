<?php

use App\Models\AppSetting;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    AppSetting::forgetCache();
});

test('app bill uses system settings without hardcoded logo fallback', function () {
    AppSetting::query()->delete();
    AppSetting::forgetCache();

    AppSetting::factory()->create([
        'company_name' => 'Rose Garden Perfume',
        'phone' => '0700111222',
        'phone_2' => '0700333444',
        'email' => 'shop@example.test',
        'website' => 'https://example.test',
        'address' => 'Kabul Road 12',
        'logo' => null,
        'invoice_title' => 'Sale Invoice',
        'invoice_footer_text' => 'Exchange within 3 days',
        'thank_you_message' => 'Thanks for choosing us',
        'tax_number' => 'TIN-99',
        'invoice_show_logo' => true,
    ]);
    AppSetting::forgetCache();

    $bill = AppSetting::bill();

    expect($bill['name'])->toBe('Rose Garden Perfume')
        ->and($bill['phone'])->toContain('0700111222')
        ->and($bill['phone'])->toContain('0700333444')
        ->and($bill['email'])->toBe('shop@example.test')
        ->and($bill['website'])->toBe('https://example.test')
        ->and($bill['address'])->toBe('Kabul Road 12')
        ->and($bill['logo'])->toBeNull()
        ->and($bill['invoice_title'])->toBe('Sale Invoice')
        ->and($bill['invoice_footer'])->toBe('Exchange within 3 days')
        ->and($bill['thank_you_message'])->toBe('Thanks for choosing us')
        ->and($bill['tax_number'])->toBe('TIN-99')
        ->and($bill['slogan'])->not->toContain('Tezhoshan');
});

test('app bill strips Tezhoshan Support placeholder from store slogan', function () {
    AppSetting::query()->delete();
    AppSetting::forgetCache();

    AppSetting::factory()->create([
        'company_name' => 'ZUHA PERFUME',
        'company_name_local' => 'Tezhoshan Support',
        'phone' => '0744442863',
        'logo' => null,
    ]);
    AppSetting::forgetCache();

    $bill = AppSetting::bill();

    expect($bill['slogan'])->toBe('')
        ->and($bill['name'])->toBe('ZUHA PERFUME')
        ->and(strtolower($bill['slogan']))->not->toContain('tezhoshan');
});

test('app bill omits store phone when settings phone is empty', function () {
    AppSetting::query()->delete();
    AppSetting::forgetCache();

    AppSetting::factory()->create([
        'company_name' => 'Empty Phone Shop',
        'phone' => null,
        'phone_2' => null,
        'logo' => null,
    ]);
    AppSetting::forgetCache();

    $bill = AppSetting::bill();

    expect($bill['phone'])->toBe('')
        ->and($bill['logo'])->toBeNull()
        ->and($bill['name'])->toBe('Empty Phone Shop');
});

test('fully paid sale receipt exposes consistent totals and company settings', function () {
    AppSetting::query()->delete();
    AppSetting::factory()->create([
        'company_name' => 'ZUHA PERFUME TEST',
        'phone' => '0744442863',
        'address' => 'Test Street',
        'email' => 'receipt@test.local',
        'logo' => null,
        'thank_you_message' => 'Thank you for shopping',
        'invoice_footer_text' => 'Keep this receipt',
    ]);
    AppSetting::forgetCache();

    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'role' => User::ROLE_SALESPERSON,
        'branch_id' => $branch->id,
    ]);

    $sale = Sale::factory()->create([
        'branch_id' => $branch->id,
        'created_by' => $user->id,
        'invoice_number' => 'INV-20261123-0002',
        'subtotal_afn' => 14000,
        'discount_afn' => 0,
        'total_afn' => 14000,
        'new_sale_amount_afn' => 14000,
        'grand_total_due_afn' => 14000,
        'received_now_afn' => 5000, // stale first-payment snapshot — must NOT drive Paid
        'paid_afn' => 14000,
        'due_afn' => 0,
        'remaining_due_afn' => 0,
        'previous_due_afn' => 0,
        'payment_status' => 'paid',
        'sale_currency_code' => 'AFN',
        'sale_currency_symbol' => '؋',
        'sale_amount_currency' => 14000,
    ]);

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_name_snapshot' => 'Creed Aventus E2E - 100ml',
        'quantity' => 1,
        'sale_price_afn' => 14000,
        'discount_afn' => 0,
        'line_total_afn' => 14000,
        'cost_price_afn' => 8000,
        'investor_profit_afn' => 0,
        'store_profit_afn' => 6000,
    ]);

    $this->actingAs($user)
        ->get(route('sales.receipt', $sale))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sales/receipt')
            ->where('sale.invoice_number', 'INV-20261123-0002')
            ->where('sale.payment_status', 'paid')
            ->where('sale.display_subtotal', 14000)
            ->where('sale.display_total', 14000)
            ->where('sale.display_grand_total_due', 14000)
            ->where('sale.display_paid', 14000)
            ->where('sale.display_remaining', 0)
            ->where('company.name', 'ZUHA PERFUME TEST')
            ->where('company.phone', '0744442863')
            ->where('company.address', 'Test Street')
            ->where('company.email', 'receipt@test.local')
            ->where('company.thank_you_message', 'Thank you for shopping')
            ->where('company.invoice_footer', 'Keep this receipt')
            ->where('company.logo', null)
            ->where('company.slogan', '')
            ->where('company.email', 'receipt@test.local')
        );
});

test('partial and unpaid sale receipts expose correct remaining and status', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'role' => User::ROLE_SALESPERSON,
        'branch_id' => $branch->id,
    ]);

    $partial = Sale::factory()->create([
        'branch_id' => $branch->id,
        'created_by' => $user->id,
        'subtotal_afn' => 10000,
        'total_afn' => 10000,
        'new_sale_amount_afn' => 10000,
        'grand_total_due_afn' => 10000,
        'received_now_afn' => 6000,
        'paid_afn' => 6000,
        'due_afn' => 4000,
        'remaining_due_afn' => 4000,
        'payment_status' => 'partial',
        'sale_currency_code' => 'AFN',
        'sale_amount_currency' => 10000,
    ]);

    $this->actingAs($user)
        ->get(route('sales.receipt', $partial))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sale.payment_status', 'partial')
            ->where('sale.display_grand_total_due', 10000)
            ->where('sale.display_paid', 6000)
            ->where('sale.display_remaining', 4000)
        );

    $unpaid = Sale::factory()->create([
        'branch_id' => $branch->id,
        'created_by' => $user->id,
        'subtotal_afn' => 10000,
        'total_afn' => 10000,
        'new_sale_amount_afn' => 10000,
        'grand_total_due_afn' => 10000,
        'received_now_afn' => 0,
        'paid_afn' => 0,
        'due_afn' => 10000,
        'remaining_due_afn' => 10000,
        'payment_status' => 'unpaid',
        'sale_currency_code' => 'AFN',
        'sale_amount_currency' => 10000,
    ]);

    $this->actingAs($user)
        ->get(route('sales.receipt', $unpaid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sale.payment_status', 'unpaid')
            ->where('sale.display_grand_total_due', 10000)
            ->where('sale.display_paid', 0)
            ->where('sale.display_remaining', 10000)
        );
});

test('multi-item sale receipt subtotal matches line totals', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'role' => User::ROLE_SALESPERSON,
        'branch_id' => $branch->id,
    ]);

    // 2×1000 + 1×2500 = 4500
    $sale = Sale::factory()->create([
        'branch_id' => $branch->id,
        'created_by' => $user->id,
        'subtotal_afn' => 4500,
        'discount_afn' => 0,
        'total_afn' => 4500,
        'new_sale_amount_afn' => 4500,
        'grand_total_due_afn' => 4500,
        'paid_afn' => 4500,
        'received_now_afn' => 4500,
        'due_afn' => 0,
        'remaining_due_afn' => 0,
        'payment_status' => 'paid',
        'sale_currency_code' => 'AFN',
        'sale_amount_currency' => 4500,
    ]);

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_name_snapshot' => 'Item A',
        'quantity' => 2,
        'sale_price_afn' => 1000,
        'line_total_afn' => 2000,
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_name_snapshot' => 'Item B',
        'quantity' => 1,
        'sale_price_afn' => 2500,
        'line_total_afn' => 2500,
    ]);

    $lineSum = (float) $sale->items()->sum('line_total_afn');
    expect($lineSum)->toBe(4500.0)
        ->and((float) $sale->subtotal_afn)->toBe(4500.0);

    $this->actingAs($user)
        ->get(route('sales.receipt', $sale))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sale.display_subtotal', 4500)
            ->where('sale.display_grand_total_due', 4500)
            ->where('sale.display_paid', 4500)
            ->where('sale.display_remaining', 0)
            ->has('sale.items', 2)
        );
});

test('updated system settings appear on new receipts', function () {
    $setting = AppSetting::query()->first() ?? AppSetting::factory()->create([
        'company_name' => 'Old Name',
        'phone' => '0700000000',
    ]);
    $setting->update([
        'company_name' => 'Old Name',
        'phone' => '0700000000',
        'address' => 'Old Address',
    ]);
    AppSetting::forgetCache();

    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'branch_id' => null,
    ]);
    $sale = Sale::factory()->create([
        'branch_id' => $branch->id,
        'created_by' => $user->id,
        'sale_currency_code' => 'AFN',
        'sale_amount_currency' => 100,
        'subtotal_afn' => 100,
        'total_afn' => 100,
        'paid_afn' => 100,
        'remaining_due_afn' => 0,
        'grand_total_due_afn' => 100,
    ]);

    $setting->update([
        'company_name' => 'New Store Name',
        'phone' => '0799999999',
        'address' => 'New Address Line',
        'thank_you_message' => 'New thank you',
    ]);
    AppSetting::forgetCache();

    $this->actingAs($user)
        ->get(route('sales.receipt', $sale))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('company.name', 'New Store Name')
            ->where('company.phone', '0799999999')
            ->where('company.address', 'New Address Line')
            ->where('company.thank_you_message', 'New thank you')
        );
});
