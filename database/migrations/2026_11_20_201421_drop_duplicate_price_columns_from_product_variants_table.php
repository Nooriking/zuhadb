<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent twin of 2026_11_20_201400_* variants cleanup.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_variants')) {
            return;
        }

        foreach ([
            'product_variants_purchase_currency_id_foreign',
            'product_variants_sale_currency_id_foreign',
            'product_variants_wholesale_currency_id_foreign',
            'product_variants_catalog_currency_id_foreign',
        ] as $fk) {
            $this->dropForeignIfExists('product_variants', $fk);
        }

        $drop = [
            'purchase_price_afn',
            'purchase_amount_usd',
            'purchase_amount_aed',
            'purchase_amount',
            'purchase_currency_id',
            'sale_price_afn',
            'sale_amount_usd',
            'sale_amount_aed',
            'sale_amount',
            'sale_currency_id',
            'wholesale_price_afn',
            'wholesale_amount_usd',
            'wholesale_amount_aed',
            'wholesale_amount',
            'wholesale_currency_id',
            'catalog_currency_id',
            'wholesale_min_qty',
            'profit_per_unit',
            'catalog_usd_to_afn_rate',
            'catalog_aed_to_afn_rate',
            'catalog_usd_to_aed_rate',
            'catalog_fx_effective_at',
            'catalog_fx_converted_at',
            'catalog_fx_source',
        ];

        $existing = array_values(array_filter(
            $drop,
            fn (string $column) => Schema::hasColumn('product_variants', $column)
        ));

        if ($existing === []) {
            return;
        }

        Schema::table('product_variants', function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    }

    public function down(): void
    {
        // Irreversible companion; leave schema as-is.
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
