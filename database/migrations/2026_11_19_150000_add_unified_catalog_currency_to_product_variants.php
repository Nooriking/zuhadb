<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive catalog pricing support:
 * - one unified catalog_currency_id per variant
 * - stored AFN/USD/AED equivalents for purchase / sale / wholesale
 * - FX board snapshot used for the catalog conversion (reference only)
 *
 * Does not rewrite purchase batches, sales, FIFO, or accounting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variants', 'catalog_currency_id')) {
                $table->foreignId('catalog_currency_id')
                    ->nullable()
                    ->after('wholesale_currency_id')
                    ->constrained('currencies')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('product_variants', 'purchase_amount_usd')) {
                $table->decimal('purchase_amount_usd', 18, 4)->nullable()->after('purchase_price_afn');
            }
            if (! Schema::hasColumn('product_variants', 'purchase_amount_aed')) {
                $table->decimal('purchase_amount_aed', 18, 4)->nullable()->after('purchase_amount_usd');
            }

            if (! Schema::hasColumn('product_variants', 'sale_amount_usd')) {
                $table->decimal('sale_amount_usd', 18, 4)->nullable()->after('sale_price_afn');
            }
            if (! Schema::hasColumn('product_variants', 'sale_amount_aed')) {
                $table->decimal('sale_amount_aed', 18, 4)->nullable()->after('sale_amount_usd');
            }

            if (! Schema::hasColumn('product_variants', 'wholesale_amount_usd')) {
                $table->decimal('wholesale_amount_usd', 18, 4)->nullable()->after('wholesale_price_afn');
            }
            if (! Schema::hasColumn('product_variants', 'wholesale_amount_aed')) {
                $table->decimal('wholesale_amount_aed', 18, 4)->nullable()->after('wholesale_amount_usd');
            }

            if (! Schema::hasColumn('product_variants', 'catalog_usd_to_afn_rate')) {
                $table->decimal('catalog_usd_to_afn_rate', 18, 8)->nullable();
            }
            if (! Schema::hasColumn('product_variants', 'catalog_aed_to_afn_rate')) {
                $table->decimal('catalog_aed_to_afn_rate', 18, 8)->nullable();
            }
            if (! Schema::hasColumn('product_variants', 'catalog_usd_to_aed_rate')) {
                $table->decimal('catalog_usd_to_aed_rate', 18, 8)->nullable();
            }
            if (! Schema::hasColumn('product_variants', 'catalog_fx_effective_at')) {
                $table->dateTime('catalog_fx_effective_at')->nullable();
            }
            if (! Schema::hasColumn('product_variants', 'catalog_fx_converted_at')) {
                $table->dateTime('catalog_fx_converted_at')->nullable();
            }
            if (! Schema::hasColumn('product_variants', 'catalog_fx_source')) {
                $table->string('catalog_fx_source', 64)->nullable();
            }
        });

        // Safe legacy backfill: only when all three currency IDs agree (or only one is set).
        // Mixed-currency legacy rows stay null until an editor re-saves with a unified currency.
        if (Schema::hasColumn('product_variants', 'catalog_currency_id')) {
            DB::statement("
                UPDATE product_variants
                SET catalog_currency_id = COALESCE(
                    CASE
                        WHEN purchase_currency_id IS NOT NULL
                            AND (sale_currency_id IS NULL OR sale_currency_id = purchase_currency_id)
                            AND (wholesale_currency_id IS NULL OR wholesale_currency_id = purchase_currency_id)
                        THEN purchase_currency_id
                        WHEN sale_currency_id IS NOT NULL
                            AND (purchase_currency_id IS NULL OR purchase_currency_id = sale_currency_id)
                            AND (wholesale_currency_id IS NULL OR wholesale_currency_id = sale_currency_id)
                        THEN sale_currency_id
                        WHEN wholesale_currency_id IS NOT NULL
                            AND (purchase_currency_id IS NULL OR purchase_currency_id = wholesale_currency_id)
                            AND (sale_currency_id IS NULL OR sale_currency_id = wholesale_currency_id)
                        THEN wholesale_currency_id
                        ELSE NULL
                    END,
                    sale_currency_id,
                    purchase_currency_id,
                    wholesale_currency_id
                )
                WHERE catalog_currency_id IS NULL
                  AND (
                    purchase_currency_id IS NOT NULL
                    OR sale_currency_id IS NOT NULL
                    OR wholesale_currency_id IS NOT NULL
                  )
                  AND (
                    (
                        COALESCE(purchase_currency_id, sale_currency_id, wholesale_currency_id)
                        = COALESCE(sale_currency_id, purchase_currency_id, wholesale_currency_id)
                    )
                    AND (
                        COALESCE(purchase_currency_id, wholesale_currency_id, sale_currency_id)
                        = COALESCE(wholesale_currency_id, purchase_currency_id, sale_currency_id)
                    )
                  )
            ");
        }
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $columns = [
                'catalog_currency_id',
                'purchase_amount_usd',
                'purchase_amount_aed',
                'sale_amount_usd',
                'sale_amount_aed',
                'wholesale_amount_usd',
                'wholesale_amount_aed',
                'catalog_usd_to_afn_rate',
                'catalog_aed_to_afn_rate',
                'catalog_usd_to_aed_rate',
                'catalog_fx_effective_at',
                'catalog_fx_converted_at',
                'catalog_fx_source',
            ];

            $existing = array_values(array_filter(
                $columns,
                fn (string $column) => Schema::hasColumn('product_variants', $column)
            ));

            if ($existing === []) {
                return;
            }

            if (in_array('catalog_currency_id', $existing, true)) {
                $table->dropConstrainedForeignId('catalog_currency_id');
                $existing = array_values(array_filter(
                    $existing,
                    fn (string $column) => $column !== 'catalog_currency_id'
                ));
            }

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
