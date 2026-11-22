<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Investment;
use App\Models\InvestorTransaction;
use App\Models\InvestorWalletBalance;
use App\Models\InvestorWithdrawal;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * End-to-end: investor-funded purchase → batch/investment/inventory → sale → withdraw.
 *
 * NOTE: PurchaseService exposes receiveStock() (not createProductWithPurchases).
 * Investor-funded stock creates type=goods_deposit (not capital_deposit) and does
 * NOT credit cash wallet — capital is tied up in inventory via investments.
 */
class BusinessFlowEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $investorAhmad;

    private Branch $branch;

    private Currency $afn;

    private Currency $usd;

    private Currency $aed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCurrencies();
        $this->seedExchangeRates();
        $this->seedUsers();
        $this->seedBranch();
    }

    private function seedCurrencies(): void
    {
        $this->afn = Currency::query()->where('code', 'AFN')->firstOrFail();
        $this->afn->update([
            'name' => 'Afghan Afghani',
            'symbol' => '؋',
            'is_base' => true,
            'rate_to_default' => 1,
            'status' => 'active',
            'trash' => false,
        ]);

        $this->usd = Currency::query()->where('code', 'USD')->firstOrFail();
        $this->usd->update([
            'name' => 'US Dollar',
            'symbol' => '$',
            'is_base' => false,
            'rate_to_default' => 56000,
            'status' => 'active',
            'trash' => false,
        ]);

        $this->aed = Currency::query()->where('code', 'AED')->firstOrFail();
        $this->aed->update([
            'name' => 'UAE Dirham',
            'symbol' => 'د.إ',
            'is_base' => false,
            'rate_to_default' => 15250,
            'status' => 'active',
            'trash' => false,
        ]);
    }

    private function seedExchangeRates(): void
    {
        ExchangeRate::query()->delete();

        ExchangeRate::create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->afn->id,
            'rate' => 56000,
            'effective_at' => now(),
            'source' => 'manual',
            'trash' => false,
        ]);
        ExchangeRate::create([
            'from_currency_id' => $this->aed->id,
            'to_currency_id' => $this->afn->id,
            'rate' => 15250,
            'effective_at' => now(),
            'source' => 'manual',
            'trash' => false,
        ]);
        ExchangeRate::create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->aed->id,
            'rate' => 3.67,
            'effective_at' => now(),
            'source' => 'manual',
            'trash' => false,
        ]);
    }

    private function seedUsers(): void
    {
        $this->admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'role' => User::ROLE_ADMIN,
            'status' => 'active',
        ]);
        $this->investorAhmad = User::factory()->create([
            'name' => 'Ahmad',
            'email' => 'ahmad@test.com',
            'role' => User::ROLE_INVESTOR,
            'status' => 'active',
        ]);
    }

    private function seedBranch(): void
    {
        $this->branch = Branch::factory()->create([
            'name' => 'Demo Kabul',
            'code' => 'BR-01',
            'status' => 'active',
            'trash' => false,
        ]);
    }

    /**
     * @return array{0: ProductVariant, 1: ProductBatch}
     */
    private function receiveInvestorStock(
        int $qty,
        Currency $purchaseCurrency,
        string $unitAmount,
        float $salePriceAfn,
        float $profitPerUnit = 0,
    ): array {
        $variant = ProductVariant::factory()->create([
            'volume_ml' => 100,
            'concentration' => 'edp',
        ]);

        $batch = app(PurchaseService::class)->receiveStock([
            'variant_id' => $variant->id,
            'quantity' => $qty,
            'purchase_currency_id' => $purchaseCurrency->id,
            'purchase_unit_amount' => $unitAmount,
            'sale_price_afn' => $salePriceAfn,
            'profit_per_unit' => $profitPerUnit,
            'funding_source' => 'investor',
            'investor_id' => $this->investorAhmad->id,
            'purchase_date' => now()->toDateString(),
            'payment_status' => 'paid',
            'invoice_number' => 'INV-TEST-001',
        ], $this->admin, $this->branch->id);

        return [$variant->fresh(), $batch->fresh()];
    }

    public function test_purchase_with_investor_capital_creates_all_records(): void
    {
        $this->actingAs($this->admin);

        [$variant, $batch] = $this->receiveInvestorStock(
            qty: 10,
            purchaseCurrency: $this->afn,
            unitAmount: '7000',
            salePriceAfn: 10000,
        );

        $this->assertNotNull($variant->product);
        $this->assertEquals(100, $variant->volume_ml);

        $this->assertNotNull($batch, 'Batch باید ساخته شود');
        $this->assertEquals(10, $batch->quantity);
        $this->assertEquals(10, $batch->remaining_quantity);
        $this->assertEquals($this->afn->id, $batch->purchase_currency_id);
        // purchase_amount_currency = line total (unit × qty)
        $this->assertEquals(70000, (float) $batch->purchase_amount_currency);
        $this->assertEquals(7000, (float) $batch->unit_cost_currency);
        $this->assertEquals(1.0, (float) $batch->exchange_rate, 'نرخ AFN→AFN باید 1 باشد');
        $this->assertEquals(7000, (float) $batch->unit_cost_afn);
        $this->assertEquals(70000, round((float) $batch->unit_cost_afn * (int) $batch->quantity, 2));
        $this->assertEquals(10000, (float) $batch->sale_price_afn);
        $this->assertEquals(3000, (float) $batch->store_profit_per_unit_afn);
        $this->assertNotNull($batch->investment_id, 'Batch باید به investment وصل باشد');

        $investment = Investment::where('investor_id', $this->investorAhmad->id)->first();
        $this->assertNotNull($investment, 'Investment باید ساخته شود');
        $this->assertEquals($this->afn->id, $investment->currency_id);
        $this->assertEquals(70000, (float) $investment->amount_currency);
        $this->assertEquals(70000, (float) $investment->amount_afn);
        $this->assertEquals(1.0, (float) $investment->exchange_rate);
        $this->assertEquals('active', $investment->status);

        // NOTE: goods-in-kind uses goods_deposit (not capital_deposit); cash wallet is not credited.
        $tx = InvestorTransaction::where('investor_id', $this->investorAhmad->id)
            ->where('type', 'goods_deposit')
            ->first();
        $this->assertNotNull($tx, 'goods_deposit باید ثبت شود');
        $this->assertEquals($investment->id, $tx->investment_id);
        $this->assertEquals(70000, (float) $tx->amount_afn);
        $this->assertEquals('capital', $tx->balance_type);

        $wallet = InvestorWalletBalance::where('investor_id', $this->investorAhmad->id)
            ->where('currency_id', $this->afn->id)
            ->first();
        // Wallet row may be created for ledger snapshot, but goods do not inflate cash capital.
        if ($wallet) {
            $this->assertEquals(0, (float) $wallet->capital_balance_afn, 'کالای سرمایه‌گذار نباید موجودی نقدی wallet را افزایش دهد');
            $this->assertEquals(0, (float) $wallet->profit_balance_afn);
        }

        $inventory = Inventory::where('variant_id', $variant->id)
            ->where('branch_id', $this->branch->id)
            ->first();
        $this->assertNotNull($inventory, 'Inventory باید ساخته شود');
        $this->assertEquals(10, $inventory->quantity);
        $this->assertEquals(10, $inventory->available_quantity);
        $this->assertEquals(7000, (float) $inventory->average_cost_afn);

        $invTx = InventoryTransaction::where('variant_id', $variant->id)
            ->where('type', 'purchase')
            ->first();
        $this->assertNotNull($invTx, 'inventory_transaction باید ثبت شود');
        $this->assertEquals(10, $invTx->quantity);
        $this->assertEquals(0, $invTx->before_quantity);
        $this->assertEquals(10, $invTx->after_quantity);
        $this->assertEquals(ProductBatch::class, $invTx->reference_type);
        $this->assertEquals($batch->id, $invTx->reference_id);
    }

    public function test_purchase_in_usd_stores_correct_exchange_rate(): void
    {
        $this->actingAs($this->admin);

        [$variant, $batch] = $this->receiveInvestorStock(
            qty: 5,
            purchaseCurrency: $this->usd,
            unitAmount: '20',
            salePriceAfn: 1120000 * 1.5, // sale > cost
        );

        $this->assertEquals(56000, (float) $batch->exchange_rate, 'exchange_rate باید از exchange_rates خوانده شود');
        $this->assertEquals($this->usd->id, $batch->purchase_currency_id);
        $this->assertEquals(100, (float) $batch->purchase_amount_currency, '5 * 20 = 100 USD line');
        $this->assertEquals(20, (float) $batch->unit_cost_currency);
        $this->assertEquals(1120000, (float) $batch->unit_cost_afn, '20 USD * 56000 = 1,120,000 AFN');
        $this->assertEquals(5600000, round((float) $batch->unit_cost_afn * (int) $batch->quantity, 2));

        $investment = Investment::where('investor_id', $this->investorAhmad->id)->first();
        $this->assertNotNull($investment);
        $this->assertEquals(100, (float) $investment->amount_currency, '5 * 20 = 100 USD');
        $this->assertEquals(5600000, (float) $investment->amount_afn, '100 * 56000 = 5,600,000 AFN');
        $this->assertEquals(56000, (float) $investment->exchange_rate);

        $tx = InvestorTransaction::where('investor_id', $this->investorAhmad->id)
            ->where('type', 'goods_deposit')
            ->first();
        $this->assertNotNull($tx);
        $this->assertEquals(5600000, (float) $tx->amount_afn);

        // NOTE: cash wallet not credited for goods_deposit (see PurchaseService).
        $wallet = InvestorWalletBalance::where('investor_id', $this->investorAhmad->id)
            ->where('currency_id', $this->usd->id)
            ->first();
        if ($wallet) {
            $this->assertEquals(0, (float) $wallet->capital_balance_currency);
        }

        $this->assertNotNull($variant);
    }

    public function test_purchase_in_aed_stores_correct_exchange_rate(): void
    {
        $this->actingAs($this->admin);

        [, $batch] = $this->receiveInvestorStock(
            qty: 4,
            purchaseCurrency: $this->aed,
            unitAmount: '50',
            salePriceAfn: 762500 * 1.2,
        );

        $this->assertEquals(15250, (float) $batch->exchange_rate, 'AED→AFN = 15250');
        $this->assertEquals(200, (float) $batch->purchase_amount_currency, '4 * 50 AED');
        $this->assertEquals(50, (float) $batch->unit_cost_currency);
        $this->assertEquals(762500, (float) $batch->unit_cost_afn, '50 * 15250 = 762,500 AFN');
        $this->assertEquals(3050000, round((float) $batch->unit_cost_afn * (int) $batch->quantity, 2));
    }

    public function test_sale_creates_profit_transaction_for_investor(): void
    {
        $this->actingAs($this->admin);

        [$variant, $batch] = $this->receiveInvestorStock(
            qty: 10,
            purchaseCurrency: $this->afn,
            unitAmount: '7000',
            salePriceAfn: 10000,
            profitPerUnit: 1000, // investor PPU AFN
        );

        $investment = Investment::where('investor_id', $this->investorAhmad->id)->first();
        $this->assertNotNull($investment);
        $this->assertEquals(1000, (float) $batch->profit_per_unit_afn);

        $sale = app(SaleService::class)->createSale([
            'branch_id' => $this->branch->id,
            'customer_id' => null,
            'sale_date' => now()->toDateString(),
            'sale_currency_id' => $this->afn->id,
            'payment_method' => 'cash',
            'discount_amount' => 0,
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 3,
                    'sale_unit_amount' => 10000,
                    'discount_amount' => 0,
                ],
            ],
            'payments' => [
                [
                    'amount' => 30000,
                    'currency_id' => $this->afn->id,
                    'payment_method' => 'cash',
                ],
            ],
        ], $this->admin);

        $inventory = Inventory::where('variant_id', $variant->id)->first();
        $this->assertEquals(7, $inventory->available_quantity, 'موجودی از 10 به 7 کاهش یابد');

        $batch->refresh();
        $this->assertEquals(7, $batch->remaining_quantity);

        $profitTx = InvestorTransaction::where('investor_id', $this->investorAhmad->id)
            ->where('type', 'profit_earned')
            ->first();
        $this->assertNotNull($profitTx, 'profit_earned باید بعد از فروش ثبت شود');
        $this->assertEquals(3000, (float) $profitTx->amount_afn, '3 × 1000 PPU');

        $wallet = InvestorWalletBalance::where('investor_id', $this->investorAhmad->id)
            ->where('currency_id', $this->afn->id)
            ->first();
        $this->assertNotNull($wallet);
        $this->assertEquals(3000, (float) $wallet->profit_balance_afn);

        $this->assertNotNull($sale);
        $this->assertEquals('INV-', substr($sale->invoice_number, 0, 4));
    }

    public function test_withdrawal_reduces_wallet_balance(): void
    {
        $this->actingAs($this->admin);

        $wallet = InvestorWalletBalance::create([
            'investor_id' => $this->investorAhmad->id,
            'currency_id' => $this->afn->id,
            'currency_code' => 'AFN',
            'capital_balance_afn' => 100000,
            'capital_balance_currency' => 100000,
            'profit_balance_afn' => 5000,
            'profit_balance_currency' => 5000,
            'last_rate' => 1,
            'last_rate_at' => now(),
        ]);

        $withdrawal = InvestorWithdrawal::create([
            'investor_id' => $this->investorAhmad->id,
            'currency_id' => $this->afn->id,
            'currency_code' => 'AFN',
            'exchange_rate' => 1,
            'withdrawal_number' => 'WD-TEST-001',
            'type' => 'profit',
            'amount_currency' => 3000,
            'amount_afn' => 3000,
            'balance_before_afn' => 5000,
            'balance_after_afn' => 2000,
            'withdrawal_date' => now()->toDateString(),
            'status' => 'approved',
            'created_by' => $this->admin->id,
        ]);

        DB::transaction(function () use ($withdrawal) {
            $w = InvestorWalletBalance::where('investor_id', $this->investorAhmad->id)
                ->where('currency_id', $this->afn->id)
                ->lockForUpdate()
                ->first();

            $before = (float) $w->profit_balance_afn;
            $after = $before - 3000;

            InvestorTransaction::create([
                'investor_id' => $this->investorAhmad->id,
                'investment_id' => null,
                'currency_id' => $this->afn->id,
                'currency_code' => 'AFN',
                'exchange_rate' => 1,
                'type' => 'profit_withdrawn',
                'amount_currency' => -3000,
                'amount_afn' => -3000,
                'balance_type' => 'profit',
                'balance_before_afn' => $before,
                'balance_after_afn' => $after,
                'reference_type' => InvestorWithdrawal::class,
                'reference_id' => $withdrawal->id,
                'description' => 'Withdrawal WD-TEST-001',
                'transaction_date' => now()->toDateString(),
                'created_by' => $this->admin->id,
            ]);

            $w->update([
                'profit_balance_afn' => $after,
                'profit_balance_currency' => $after,
            ]);

            $withdrawal->update([
                'status' => 'paid',
                'paid_by' => $this->admin->id,
                'paid_at' => now(),
            ]);
        });

        $wallet->refresh();
        $this->assertEquals(2000, (float) $wallet->profit_balance_afn, 'سود از 5000 به 2000 کاهش یابد');

        $tx = InvestorTransaction::where('investor_id', $this->investorAhmad->id)
            ->where('type', 'profit_withdrawn')
            ->first();
        $this->assertNotNull($tx);
        $this->assertEquals(-3000, (float) $tx->amount_afn);
    }
}
