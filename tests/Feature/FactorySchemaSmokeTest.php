<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\InvestorTransaction;
use App\Models\InvestorWithdrawal;
use App\Models\ProductBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FactorySchemaSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::query()->updateOrCreate(
            ['code' => 'AFN'],
            ['name' => 'Afghani', 'symbol' => '؋', 'decimal_places' => 2, 'rate_to_default' => 1, 'is_base' => true, 'status' => 'active', 'trash' => false]
        );
        Currency::query()->updateOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'rate_to_default' => 0.00001786, 'is_base' => false, 'status' => 'active', 'trash' => false]
        );
        Currency::query()->updateOrCreate(
            ['code' => 'AED'],
            ['name' => 'Dirham', 'symbol' => 'د.إ', 'decimal_places' => 2, 'rate_to_default' => 0.00006557, 'is_base' => false, 'status' => 'active', 'trash' => false]
        );
    }

    public function test_investment_factory_matches_slim_schema(): void
    {
        $investment = Investment::factory()->create();

        $this->assertDatabaseHas('investments', [
            'id' => $investment->id,
            'currency_id' => $investment->currency_id,
        ]);
        $this->assertNotNull($investment->amount_currency);
        $this->assertNotNull($investment->amount_afn);
        $this->assertFalse(Schema::hasColumn('investments', 'source_type'));
        $this->assertFalse(Schema::hasColumn('investments', 'investment_number'));
    }

    public function test_product_batch_factory_sets_purchase_currency(): void
    {
        $batch = ProductBatch::factory()->create();

        $this->assertNotNull($batch->purchase_currency_id);
        $this->assertNotNull($batch->investment_id);
        $this->assertEquals(1.0, (float) $batch->exchange_rate);
        $this->assertFalse(Schema::hasColumn('product_batches', 'investor_id'));
        $this->assertFalse(Schema::hasColumn('product_batches', 'usd_to_afn_rate'));
    }

    public function test_investor_transaction_factory_creates_capital_deposit(): void
    {
        $txn = InvestorTransaction::factory()->capitalDeposit()->create();

        $this->assertEquals('capital_deposit', $txn->type);
        $this->assertEquals('AFN', $txn->currency_code);
        $this->assertGreaterThan(0, (float) $txn->amount_afn);
    }

    public function test_investor_withdrawal_factory_creates_row(): void
    {
        $wd = InvestorWithdrawal::factory()->create([
            'investor_id' => Investor::factory(),
        ]);

        $this->assertEquals('pending', $wd->status);
        $this->assertEquals('AFN', $wd->currency_code);
    }

    public function test_dead_module_factories_are_removed(): void
    {
        $dead = [
            'InvestorProfitEntryFactory.php',
            'CustomerTransactionFactory.php',
            'InvestmentProductAllocationFactory.php',
            'InvestmentPurchaseAllocationFactory.php',
            'InvestorWalletFactory.php',
        ];

        foreach ($dead as $file) {
            $this->assertFileDoesNotExist(
                database_path('factories/'.$file),
                "Dead factory still present: {$file}"
            );
        }
    }
}
