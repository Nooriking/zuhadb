<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent twin of 2026_11_20_201400_*: safe if columns/FKs already removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        foreach ([
            'products_variant_id_foreign',
            'products_investor_id_foreign',
            'products_supplier_id_foreign',
            'products_purchase_currency_id_foreign',
            'products_profit_currency_id_foreign',
        ] as $fk) {
            $this->dropForeignIfExists('products', $fk);
        }

        $drop = [
            'exchange_rate',
            'purchase_currency_id',
            'purchase_currency_code',
            'purchase_currency_symbol',
            'purchase_unit_amount',
            'purchase_line_amount',
            'rate_to_ledger',
            'rate_kind',
            'usd_to_afn_rate',
            'aed_to_afn_rate',
            'usd_to_aed_rate',
            'rate_effective_at',
            'rate_source',
            'unit_amount_usd',
            'unit_amount_aed',
            'line_amount_usd',
            'line_amount_aed',
            'purchase_price_afn',
            'wholesale_price_afn',
            'wholesale_min_qty',
            'profit_per_unit',
            'profit_currency_id',
            'profit_currency_code',
            'store_profit_per_unit',
            'line_total',
            'line_profit',
            'line_store_profit',
            'variant_id',
            'investor_id',
            'supplier_id',
        ];

        $existing = array_values(array_filter(
            $drop,
            fn (string $column) => Schema::hasColumn('products', $column)
        ));

        if ($existing === []) {
            return;
        }

        Schema::table('products', function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    }

    public function down(): void
    {
        // Irreversible companion of 201400; leave schema as-is.
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        $row = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = ?',
            [$table, $constraint, 'FOREIGN KEY']
        );

        if (! $row) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
    }
};
