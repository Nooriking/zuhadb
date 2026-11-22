<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Support\ProductBatchPurchaseCurrencyBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductBatchPurchaseCurrencyBackfillTest extends TestCase
{
    use RefreshDatabase;

    private Currency $afn;

    private Currency $usd;

    private Currency $aed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->afn = Currency::query()->updateOrCreate(
            ['code' => 'AFN'],
            ['name' => 'Afghani', 'symbol' => '؋', 'decimal_places' => 2, 'rate_to_default' => 1, 'is_base' => true, 'status' => 'active', 'trash' => false]
        );
        $this->usd = Currency::query()->updateOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'rate_to_default' => 0.00001786, 'is_base' => false, 'status' => 'active', 'trash' => false]
        );
        $this->aed = Currency::query()->updateOrCreate(
            ['code' => 'AED'],
            ['name' => 'Dirham', 'symbol' => 'د.إ', 'decimal_places' => 2, 'rate_to_default' => 0.00006557, 'is_base' => false, 'status' => 'active', 'trash' => false]
        );

        DB::table('exchange_rates')->insert([
            [
                'from_currency_id' => $this->usd->id,
                'to_currency_id' => $this->afn->id,
                'rate' => 56000,
                'effective_at' => now()->subDay(),
                'source' => 'manual',
                'trash' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'from_currency_id' => $this->aed->id,
                'to_currency_id' => $this->afn->id,
                'rate' => 15250,
                'effective_at' => now()->subDay(),
                'source' => 'manual',
                'trash' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function test_backfill_infers_afn_usd_and_aed_from_exchange_rate(): void
    {
        $branch = Branch::factory()->create();
        $variant = ProductVariant::factory()->create();
        $investment = Investment::factory()->create(['currency_id' => $this->afn->id]);

        $afnBatch = ProductBatch::factory()->create([
            'branch_id' => $branch->id,
            'variant_id' => $variant->id,
            'investment_id' => $investment->id,
            'purchase_currency_id' => null,
            'exchange_rate' => null,
            'unit_cost_currency' => null,
            'unit_cost_afn' => 2800,
            'purchase_amount_currency' => null,
            'quantity' => 8,
            'remaining_quantity' => 8,
            'total_cost_afn' => 22400,
        ]);

        $usdBatch = ProductBatch::factory()->create([
            'branch_id' => $branch->id,
            'variant_id' => $variant->id,
            'investment_id' => $investment->id,
            'purchase_currency_id' => null,
            'exchange_rate' => 56000,
            'unit_cost_currency' => 2000,
            'unit_cost_afn' => 112000000,
            'purchase_amount_currency' => 20000,
            'quantity' => 10,
            'remaining_quantity' => 10,
            'total_cost_afn' => 1120000000,
        ]);

        $aedBatch = ProductBatch::factory()->create([
            'branch_id' => $branch->id,
            'variant_id' => $variant->id,
            'investment_id' => $investment->id,
            'purchase_currency_id' => null,
            'exchange_rate' => 15250,
            'unit_cost_currency' => 500,
            'unit_cost_afn' => 7625000,
            'purchase_amount_currency' => 2500,
            'quantity' => 5,
            'remaining_quantity' => 5,
            'total_cost_afn' => 38125000,
        ]);

        $result = ProductBatchPurchaseCurrencyBackfill::run();

        $this->assertSame(3, $result['updated']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(1, $result['by_code']['AFN']);
        $this->assertSame(1, $result['by_code']['USD']);
        $this->assertSame(1, $result['by_code']['AED']);

        $afnBatch->refresh();
        $usdBatch->refresh();
        $aedBatch->refresh();

        $this->assertSame($this->afn->id, (int) $afnBatch->purchase_currency_id);
        $this->assertEquals(1.0, (float) $afnBatch->exchange_rate);
        $this->assertEquals(22400.0, (float) $afnBatch->purchase_amount_currency);

        $this->assertSame($this->usd->id, (int) $usdBatch->purchase_currency_id);
        $this->assertSame($this->aed->id, (int) $aedBatch->purchase_currency_id);

        $this->assertSame(0, ProductBatch::query()->whereNull('purchase_currency_id')->count());
    }
}
