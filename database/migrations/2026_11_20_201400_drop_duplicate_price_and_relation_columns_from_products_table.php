<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Money/FX snapshots move to product_batches; catalog prices to product_prices.
 * Relation FKs (variant/investor/supplier) live on batches / invoices / pivots.
 *
 * Catalog vs purchase discriminator after this migration:
 *   purchase rows → purchase_date IS NOT NULL
 *   catalog products → purchase_date IS NULL
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $columns = [
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

    /** @var list<string> */
    private array $foreignKeys = [
        'products_variant_id_foreign',
        'products_investor_id_foreign',
        'products_supplier_id_foreign',
        'products_purchase_currency_id_foreign',
        'products_profit_currency_id_foreign',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        foreach ($this->foreignKeys as $fk) {
            $this->dropForeignIfExists('products', $fk);
        }

        // Also drop any FK on variant_id that uses a non-default name.
        foreach ($this->foreignKeysOnColumn('products', 'variant_id') as $fk) {
            $this->dropForeignIfExists('products', $fk);
        }

        $toDrop = array_values(array_filter(
            $this->columns,
            fn (string $col) => Schema::hasColumn('products', $col)
        ));

        if ($toDrop === []) {
            return;
        }

        Schema::table('products', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'variant_id')) {
                $table->unsignedBigInteger('variant_id')->nullable()->after('description');
            }
            if (! Schema::hasColumn('products', 'investor_id')) {
                $table->unsignedBigInteger('investor_id')->nullable();
            }
            if (! Schema::hasColumn('products', 'supplier_id')) {
                $table->unsignedBigInteger('supplier_id')->nullable();
            }
            if (! Schema::hasColumn('products', 'exchange_rate')) {
                $table->decimal('exchange_rate', 18, 8)->nullable();
            }
            if (! Schema::hasColumn('products', 'purchase_currency_id')) {
                $table->unsignedBigInteger('purchase_currency_id')->nullable();
            }
            if (! Schema::hasColumn('products', 'purchase_currency_code')) {
                $table->string('purchase_currency_code', 10)->nullable();
            }
            if (! Schema::hasColumn('products', 'purchase_currency_symbol')) {
                $table->string('purchase_currency_symbol', 16)->nullable();
            }
            if (! Schema::hasColumn('products', 'purchase_unit_amount')) {
                $table->decimal('purchase_unit_amount', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'purchase_line_amount')) {
                $table->decimal('purchase_line_amount', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'rate_to_ledger')) {
                $table->decimal('rate_to_ledger', 18, 8)->nullable();
            }
            if (! Schema::hasColumn('products', 'rate_kind')) {
                $table->string('rate_kind', 40)->nullable();
            }
            if (! Schema::hasColumn('products', 'usd_to_afn_rate')) {
                $table->decimal('usd_to_afn_rate', 18, 8)->nullable();
            }
            if (! Schema::hasColumn('products', 'aed_to_afn_rate')) {
                $table->decimal('aed_to_afn_rate', 18, 8)->nullable();
            }
            if (! Schema::hasColumn('products', 'usd_to_aed_rate')) {
                $table->decimal('usd_to_aed_rate', 18, 8)->nullable();
            }
            if (! Schema::hasColumn('products', 'rate_effective_at')) {
                $table->dateTime('rate_effective_at')->nullable();
            }
            if (! Schema::hasColumn('products', 'rate_source')) {
                $table->string('rate_source', 40)->nullable();
            }
            if (! Schema::hasColumn('products', 'unit_amount_usd')) {
                $table->decimal('unit_amount_usd', 18, 4)->nullable();
            }
            if (! Schema::hasColumn('products', 'unit_amount_aed')) {
                $table->decimal('unit_amount_aed', 18, 4)->nullable();
            }
            if (! Schema::hasColumn('products', 'line_amount_usd')) {
                $table->decimal('line_amount_usd', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'line_amount_aed')) {
                $table->decimal('line_amount_aed', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'purchase_price_afn')) {
                $table->decimal('purchase_price_afn', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'wholesale_price_afn')) {
                $table->decimal('wholesale_price_afn', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'wholesale_min_qty')) {
                $table->integer('wholesale_min_qty')->nullable();
            }
            if (! Schema::hasColumn('products', 'profit_per_unit')) {
                $table->decimal('profit_per_unit', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'profit_currency_id')) {
                $table->unsignedBigInteger('profit_currency_id')->nullable();
            }
            if (! Schema::hasColumn('products', 'profit_currency_code')) {
                $table->string('profit_currency_code', 10)->nullable();
            }
            if (! Schema::hasColumn('products', 'store_profit_per_unit')) {
                $table->decimal('store_profit_per_unit', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'line_total')) {
                $table->decimal('line_total', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'line_profit')) {
                $table->decimal('line_profit', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'line_store_profit')) {
                $table->decimal('line_store_profit', 18, 2)->nullable();
            }
        });
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

    /**
     * @return list<string>
     */
    private function foreignKeysOnColumn(string $table, string $column): array
    {
        $rows = DB::select(
            'SELECT DISTINCT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, $column]
        );

        return array_values(array_filter(array_map(
            static fn ($row) => (string) ($row->CONSTRAINT_NAME ?? ''),
            $rows
        )));
    }
};
