<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixed per-unit investor profit: preserve original currency + code on batches/purchase rows,
 * and explicit gross profit on sale lines (store_profit_afn becomes company residual).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_batches', function (Blueprint $table) {
            // Some environments never received the older investor-fields migration.
            if (! Schema::hasColumn('product_batches', 'profit_per_unit_currency')) {
                $table->decimal('profit_per_unit_currency', 18, 4)->default(0)->after('profit_per_unit_afn');
            }
            if (! Schema::hasColumn('product_batches', 'profit_currency_id')) {
                $table->foreignId('profit_currency_id')
                    ->nullable()
                    ->after('profit_per_unit_afn')
                    ->constrained('currencies')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('product_batches', 'profit_currency_code')) {
                $table->string('profit_currency_code', 10)->nullable()->after('profit_currency_id');
            }
            if (! Schema::hasColumn('product_batches', 'profit_per_unit_usd')) {
                $table->decimal('profit_per_unit_usd', 18, 4)->nullable()->after('profit_currency_code');
            }
            if (! Schema::hasColumn('product_batches', 'profit_per_unit_aed')) {
                $table->decimal('profit_per_unit_aed', 18, 4)->nullable()->after('profit_per_unit_usd');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'profit_currency_id')) {
                $table->foreignId('profit_currency_id')
                    ->nullable()
                    ->after('profit_per_unit')
                    ->constrained('currencies')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('products', 'profit_currency_code')) {
                $table->string('profit_currency_code', 10)->nullable()->after('profit_currency_id');
            }
        });

        Schema::table('sale_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_items', 'gross_profit_afn')) {
                $table->decimal('gross_profit_afn', 18, 2)->default(0)->after('line_total_afn');
            }
        });

        // Legacy sale_items.store_profit_afn held gross (revenue − COGS).
        // Backfill gross, then convert store_profit_afn to company residual.
        if (Schema::hasColumn('sale_items', 'gross_profit_afn')) {
            \Illuminate\Support\Facades\DB::statement(
                'UPDATE sale_items SET gross_profit_afn = store_profit_afn WHERE COALESCE(gross_profit_afn, 0) = 0'
            );
            \Illuminate\Support\Facades\DB::statement(
                'UPDATE sale_items SET store_profit_afn = ROUND(gross_profit_afn - COALESCE(investor_profit_afn, 0), 2)'
            );
        }
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            if (Schema::hasColumn('sale_items', 'gross_profit_afn')) {
                $table->dropColumn('gross_profit_afn');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'profit_currency_id')) {
                $table->dropConstrainedForeignId('profit_currency_id');
            }
            if (Schema::hasColumn('products', 'profit_currency_code')) {
                $table->dropColumn('profit_currency_code');
            }
        });

        Schema::table('product_batches', function (Blueprint $table) {
            if (Schema::hasColumn('product_batches', 'profit_currency_id')) {
                $table->dropConstrainedForeignId('profit_currency_id');
            }
            foreach (['profit_currency_code', 'profit_per_unit_usd', 'profit_per_unit_aed'] as $column) {
                if (Schema::hasColumn('product_batches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
