<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductBatchNoSnapshotColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_batches_has_no_snapshot_rate_columns(): void
    {
        $columns = Schema::getColumnListing('product_batches');

        $this->assertNotContains('usd_to_afn_rate', $columns, 'ستون usd_to_afn_rate نباید در product_batches باشد');
        $this->assertNotContains('aed_to_afn_rate', $columns, 'ستون aed_to_afn_rate نباید در product_batches باشد');
        $this->assertNotContains('usd_to_aed_rate', $columns, 'ستون usd_to_aed_rate نباید در product_batches باشد');
        $this->assertNotContains('rate_effective_at', $columns, 'ستون rate_effective_at نباید در product_batches باشد');
        $this->assertNotContains('rate_source', $columns, 'ستون rate_source نباید در product_batches باشد');

        $this->assertContains('exchange_rate', $columns, 'ستون exchange_rate باید در product_batches باشد');
    }

    public function test_investments_has_no_snapshot_columns(): void
    {
        $columns = Schema::getColumnListing('investments');

        $this->assertNotContains('capital_amount_usd', $columns);
        $this->assertNotContains('capital_amount_aed', $columns);
        $this->assertNotContains('investment_number', $columns);
        $this->assertNotContains('source_type', $columns);
        $this->assertNotContains('parent_investment_id', $columns);

        $this->assertContains('investor_id', $columns);
        $this->assertContains('currency_id', $columns);
        $this->assertContains('amount_afn', $columns);
        $this->assertContains('exchange_rate', $columns);
    }

    public function test_dropped_tables_do_not_exist(): void
    {
        $droppedTables = [
            'investors_legacy',
            'investor_user_migration_map',
            'investor_profit_entries',
            'investment_purchase_allocations',
            'investment_product_allocations',
            'investor_wallets',
            'customer_balances',
            'customer_transactions',
        ];

        foreach ($droppedTables as $table) {
            $this->assertFalse(
                Schema::hasTable($table),
                "جدول {$table} باید حذف شده باشد ولی هنوز وجود دارد"
            );
        }
    }
}
