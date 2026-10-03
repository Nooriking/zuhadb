<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ═══════════════════════════════════════════════════
         |  products — سود دوکان
         ═══════════════════════════════════════════════════ */
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'store_profit_per_unit')) {
                $table->decimal('store_profit_per_unit', 15, 2)
                    ->default(0)
                    ->after('profit_per_unit')
                    ->comment('Store profit per unit (sale - cost - investor)');
            }

            if (! Schema::hasColumn('products', 'line_store_profit')) {
                $table->decimal('line_store_profit', 15, 2)
                    ->default(0)
                    ->after('line_profit')
                    ->comment('Total store profit for this purchase line');
            }
        });

        /* ═══════════════════════════════════════════════════
         |  product_batches — قیمت فروش + سود دوکان
         ═══════════════════════════════════════════════════ */
        Schema::table('product_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('product_batches', 'sale_price_afn')) {
                $table->decimal('sale_price_afn', 15, 2)
                    ->default(0)
                    ->after('unit_cost_afn')
                    ->comment('Expected sale price at time of purchase');
            }

            if (! Schema::hasColumn('product_batches', 'store_profit_per_unit_afn')) {
                $table->decimal('store_profit_per_unit_afn', 15, 2)
                    ->default(0)
                    ->after('profit_per_unit_afn')
                    ->comment('Store profit per unit (excludes investor share)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['store_profit_per_unit', 'line_store_profit']);
        });

        Schema::table('product_batches', function (Blueprint $table) {
            $table->dropColumn(['sale_price_afn', 'store_profit_per_unit_afn']);
        });
    }
};