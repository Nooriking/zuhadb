<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prices live only in product_prices. Drop duplicate catalog money/FX
 * columns from product_variants (Phase 2 / Cost-Batch cleanup).
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $columns = [
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

    /** @var list<string> */
    private array $foreignKeys = [
        'product_variants_purchase_currency_id_foreign',
        'product_variants_sale_currency_id_foreign',
        'product_variants_wholesale_currency_id_foreign',
        'product_variants_catalog_currency_id_foreign',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('product_variants')) {
            return;
        }

        foreach ($this->foreignKeys as $fk) {
            $row = DB::selectOne(
                'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND CONSTRAINT_NAME = ?
                   AND CONSTRAINT_TYPE = ?',
                ['product_variants', $fk, 'FOREIGN KEY']
            );
            if ($row) {
                DB::statement("ALTER TABLE `product_variants` DROP FOREIGN KEY `{$fk}`");
            }
        }

        $toDrop = array_values(array_filter(
            $this->columns,
            fn (string $col) => Schema::hasColumn('product_variants', $col)
        ));

        if ($toDrop === []) {
            return;
        }

        Schema::table('product_variants', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_variants')) {
            return;
        }

        Schema::table('product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variants', 'purchase_price_afn')) {
                $table->decimal('purchase_price_afn', 18, 2)->default(0);
            }
            if (! Schema::hasColumn('product_variants', 'purchase_amount_usd')) {
                $table->decimal('purchase_amount_usd', 18, 4)->nullable();
            }
            if (! Schema::hasColumn('product_variants', 'purchase_amount_aed')) {
                $table->decimal('purchase_amount_aed', 18, 4)->nullable();
            }
            if (! Schema::hasColumn('product_variants', 'purchase_amount')) {
                $table->decimal('purchase_amount', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('product_variants', 'purchase_currency_id')) {
                $table->foreignId('purchase_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            }
            if (! Schema::hasColumn('product_variants', 'sale_price_afn')) {
                $table->decimal('sale_price_afn', 18, 2)->default(0);
            }
            if (! Schema::hasColumn('product_variants', 'sale_amount_usd')) {
                $table->decimal('sale_amount_usd', 18, 4)->nullable();
            }
            if (! Schema::hasColumn('product_variants', 'sale_amount_aed')) {
                $table->decimal('sale_amount_aed', 18, 4)->nullable();
            }
            if (! Schema::hasColumn('product_variants', 'sale_amount')) {
                $table->decimal('sale_amount', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('product_variants', 'sale_currency_id')) {
                $table->foreignId('sale_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            }
            if (! Schema::hasColumn('product_variants', 'wholesale_price_afn')) {
                $table->decimal('wholesale_price_afn', 18, 2)->default(0);
            }
            if (! Schema::hasColumn('product_variants', 'wholesale_amount_usd')) {
                $table->decimal('wholesale_amount_usd', 18, 4)->nullable();
            }
            if (! Schema::hasColumn('product_variants', 'wholesale_amount_aed')) {
                $table->decimal('wholesale_amount_aed', 18, 4)->nullable();
            }
            if (! Schema::hasColumn('product_variants', 'wholesale_amount')) {
                $table->decimal('wholesale_amount', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('product_variants', 'wholesale_currency_id')) {
                $table->foreignId('wholesale_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            }
            if (! Schema::hasColumn('product_variants', 'catalog_currency_id')) {
                $table->foreignId('catalog_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            }
            if (! Schema::hasColumn('product_variants', 'wholesale_min_qty')) {
                $table->integer('wholesale_min_qty')->default(0);
            }
            if (! Schema::hasColumn('product_variants', 'profit_per_unit')) {
                $table->decimal('profit_per_unit', 18, 2)->default(0);
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
                $table->string('catalog_fx_source', 40)->nullable();
            }
        });
    }
};
