<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forward-only: immutable FX board + three-currency equivalents on
 * purchase rows, sales, sale items, and goods investments.
 * Does not drop or rename existing financial columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'usd_to_afn_rate')) {
                    $table->decimal('usd_to_afn_rate', 18, 8)->nullable()->after('rate_kind');
                }
                if (! Schema::hasColumn('products', 'aed_to_afn_rate')) {
                    $table->decimal('aed_to_afn_rate', 18, 8)->nullable()->after('usd_to_afn_rate');
                }
                if (! Schema::hasColumn('products', 'usd_to_aed_rate')) {
                    $table->decimal('usd_to_aed_rate', 18, 8)->nullable()->after('aed_to_afn_rate');
                }
                if (! Schema::hasColumn('products', 'rate_effective_at')) {
                    $table->dateTime('rate_effective_at')->nullable()->after('usd_to_aed_rate');
                }
                if (! Schema::hasColumn('products', 'rate_source')) {
                    $table->string('rate_source', 40)->nullable()->after('rate_effective_at');
                }
                if (! Schema::hasColumn('products', 'unit_amount_usd')) {
                    $table->decimal('unit_amount_usd', 18, 4)->nullable()->after('rate_source');
                }
                if (! Schema::hasColumn('products', 'unit_amount_aed')) {
                    $table->decimal('unit_amount_aed', 18, 4)->nullable()->after('unit_amount_usd');
                }
                if (! Schema::hasColumn('products', 'line_amount_usd')) {
                    $table->decimal('line_amount_usd', 18, 2)->nullable()->after('unit_amount_aed');
                }
                if (! Schema::hasColumn('products', 'line_amount_aed')) {
                    $table->decimal('line_amount_aed', 18, 2)->nullable()->after('line_amount_usd');
                }
            });
        }

        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (! Schema::hasColumn('sales', 'sale_currency_id')) {
                    $table->foreignId('sale_currency_id')->nullable()->after('fx_rate_kind')
                        ->constrained('currencies')->nullOnDelete();
                }
                if (! Schema::hasColumn('sales', 'sale_currency_code')) {
                    $table->string('sale_currency_code', 10)->nullable()->after('sale_currency_id');
                }
                if (! Schema::hasColumn('sales', 'sale_currency_symbol')) {
                    $table->string('sale_currency_symbol', 16)->nullable()->after('sale_currency_code');
                }
                if (! Schema::hasColumn('sales', 'sale_amount_currency')) {
                    $table->decimal('sale_amount_currency', 18, 2)->nullable()->after('sale_currency_symbol');
                }
                if (! Schema::hasColumn('sales', 'usd_to_afn_rate')) {
                    $table->decimal('usd_to_afn_rate', 18, 8)->nullable()->after('sale_amount_currency');
                }
                if (! Schema::hasColumn('sales', 'aed_to_afn_rate')) {
                    $table->decimal('aed_to_afn_rate', 18, 8)->nullable()->after('usd_to_afn_rate');
                }
                if (! Schema::hasColumn('sales', 'usd_to_aed_rate')) {
                    $table->decimal('usd_to_aed_rate', 18, 8)->nullable()->after('aed_to_afn_rate');
                }
                if (! Schema::hasColumn('sales', 'rate_effective_at')) {
                    $table->dateTime('rate_effective_at')->nullable()->after('usd_to_aed_rate');
                }
                if (! Schema::hasColumn('sales', 'rate_source')) {
                    $table->string('rate_source', 40)->nullable()->after('rate_effective_at');
                }
                if (! Schema::hasColumn('sales', 'aed_equivalent_amount')) {
                    $table->decimal('aed_equivalent_amount', 18, 2)->nullable()->after('usd_equivalent_amount');
                }
            });
        }

        if (Schema::hasTable('sale_items')) {
            Schema::table('sale_items', function (Blueprint $table) {
                if (! Schema::hasColumn('sale_items', 'sale_currency_code')) {
                    $table->string('sale_currency_code', 10)->nullable()->after('barcode_snapshot');
                }
                if (! Schema::hasColumn('sale_items', 'sale_unit_amount')) {
                    $table->decimal('sale_unit_amount', 18, 4)->nullable()->after('sale_currency_code');
                }
                if (! Schema::hasColumn('sale_items', 'sale_line_amount')) {
                    $table->decimal('sale_line_amount', 18, 2)->nullable()->after('sale_unit_amount');
                }
                if (! Schema::hasColumn('sale_items', 'unit_amount_usd')) {
                    $table->decimal('unit_amount_usd', 18, 4)->nullable()->after('sale_line_amount');
                }
                if (! Schema::hasColumn('sale_items', 'unit_amount_aed')) {
                    $table->decimal('unit_amount_aed', 18, 4)->nullable()->after('unit_amount_usd');
                }
                if (! Schema::hasColumn('sale_items', 'line_amount_usd')) {
                    $table->decimal('line_amount_usd', 18, 2)->nullable()->after('unit_amount_aed');
                }
                if (! Schema::hasColumn('sale_items', 'line_amount_aed')) {
                    $table->decimal('line_amount_aed', 18, 2)->nullable()->after('line_amount_usd');
                }
            });
        }

        if (Schema::hasTable('product_batches')) {
            Schema::table('product_batches', function (Blueprint $table) {
                if (! Schema::hasColumn('product_batches', 'unit_cost_currency')) {
                    $table->decimal('unit_cost_currency', 18, 4)->nullable()->after('remaining_quantity');
                }
                if (! Schema::hasColumn('product_batches', 'exchange_rate')) {
                    $table->decimal('exchange_rate', 18, 8)->nullable()->after('unit_cost_currency');
                }
            });
        }

        if (Schema::hasTable('investments')) {
            Schema::table('investments', function (Blueprint $table) {
                if (! Schema::hasColumn('investments', 'usd_to_afn_rate')) {
                    $table->decimal('usd_to_afn_rate', 18, 8)->nullable()->after('exchange_rate');
                }
                if (! Schema::hasColumn('investments', 'aed_to_afn_rate')) {
                    $table->decimal('aed_to_afn_rate', 18, 8)->nullable()->after('usd_to_afn_rate');
                }
                if (! Schema::hasColumn('investments', 'usd_to_aed_rate')) {
                    $table->decimal('usd_to_aed_rate', 18, 8)->nullable()->after('aed_to_afn_rate');
                }
                if (! Schema::hasColumn('investments', 'rate_effective_at')) {
                    $table->dateTime('rate_effective_at')->nullable()->after('usd_to_aed_rate');
                }
                if (! Schema::hasColumn('investments', 'rate_source')) {
                    $table->string('rate_source', 40)->nullable()->after('rate_effective_at');
                }
                if (! Schema::hasColumn('investments', 'capital_amount_usd')) {
                    $table->decimal('capital_amount_usd', 18, 2)->nullable()->after('capital_amount_afn');
                }
                if (! Schema::hasColumn('investments', 'capital_amount_aed')) {
                    $table->decimal('capital_amount_aed', 18, 2)->nullable()->after('capital_amount_usd');
                }
            });
        }
    }

    public function down(): void
    {
        // Keep snapshot columns; dropping them would erase historical originals.
    }
};
