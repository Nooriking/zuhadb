<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->seedSystemCurrencies();

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'base_unit_name')) {
                    $table->string('base_unit_name', 50)->nullable()->after('description');
                }
                if (! Schema::hasColumn('products', 'pack_unit_name')) {
                    $table->string('pack_unit_name', 50)->nullable()->after('base_unit_name');
                }
                if (! Schema::hasColumn('products', 'units_per_pack')) {
                    $table->decimal('units_per_pack', 18, 6)->nullable()->after('pack_unit_name');
                }
            });
        }

        if (Schema::hasTable('product_variants')) {
            Schema::table('product_variants', function (Blueprint $table) {
                if (! Schema::hasColumn('product_variants', 'purchase_amount')) {
                    $table->decimal('purchase_amount', 14, 2)->nullable()->after('purchase_price_afn');
                }
                if (! Schema::hasColumn('product_variants', 'sale_amount')) {
                    $table->decimal('sale_amount', 14, 2)->nullable()->after('sale_price_afn');
                }
                if (! Schema::hasColumn('product_variants', 'wholesale_amount')) {
                    $table->decimal('wholesale_amount', 14, 2)->nullable()->after('wholesale_price_afn');
                }
                if (! Schema::hasColumn('product_variants', 'purchase_currency_id')) {
                    $table->foreignId('purchase_currency_id')->nullable()->after('purchase_amount')
                        ->constrained('currencies')->nullOnDelete();
                }
                if (! Schema::hasColumn('product_variants', 'sale_currency_id')) {
                    $table->foreignId('sale_currency_id')->nullable()->after('sale_amount')
                        ->constrained('currencies')->nullOnDelete();
                }
                if (! Schema::hasColumn('product_variants', 'wholesale_currency_id')) {
                    $table->foreignId('wholesale_currency_id')->nullable()->after('wholesale_amount')
                        ->constrained('currencies')->nullOnDelete();
                }
            });
        }

        $this->restoreMoneyColumns('investments', [
            'currency_id' => fn (Blueprint $table) => $table->foreignId('currency_id')->nullable()->after('investment_number')->constrained('currencies')->nullOnDelete(),
            'currency_code' => fn (Blueprint $table) => $table->string('currency_code', 10)->nullable()->after('currency_id'),
            'currency_symbol' => fn (Blueprint $table) => $table->string('currency_symbol', 16)->nullable()->after('currency_code'),
            'exchange_rate' => fn (Blueprint $table) => $table->decimal('exchange_rate', 18, 8)->nullable()->after('currency_symbol'),
            'capital_amount_currency' => fn (Blueprint $table) => $table->decimal('capital_amount_currency', 18, 2)->nullable()->after('exchange_rate'),
            'available_amount_currency' => fn (Blueprint $table) => $table->decimal('available_amount_currency', 18, 2)->nullable(),
            'profit_amount_currency' => fn (Blueprint $table) => $table->decimal('profit_amount_currency', 18, 2)->nullable(),
        ], ['currency_id', 'currency_code']);

        $this->restoreMoneyColumns('investor_transactions', [
            'currency_id' => fn (Blueprint $table) => $table->foreignId('currency_id')->nullable()->after('investment_id')->constrained('currencies')->nullOnDelete(),
            'currency_code' => fn (Blueprint $table) => $table->string('currency_code', 10)->nullable()->after('currency_id'),
            'currency_symbol' => fn (Blueprint $table) => $table->string('currency_symbol', 16)->nullable()->after('currency_code'),
            'exchange_rate' => fn (Blueprint $table) => $table->decimal('exchange_rate', 18, 8)->nullable()->after('currency_symbol'),
            'amount_currency' => fn (Blueprint $table) => $table->decimal('amount_currency', 18, 2)->nullable()->after('type'),
        ], ['currency_id', 'currency_code']);

        $this->restoreMoneyColumns('investor_profit_entries', [
            'currency_id' => fn (Blueprint $table) => $table->foreignId('currency_id')->nullable()->after('sale_item_id')->constrained('currencies')->nullOnDelete(),
            'currency_code' => fn (Blueprint $table) => $table->string('currency_code', 10)->nullable()->after('currency_id'),
            'currency_symbol' => fn (Blueprint $table) => $table->string('currency_symbol', 16)->nullable()->after('currency_code'),
            'exchange_rate' => fn (Blueprint $table) => $table->decimal('exchange_rate', 18, 8)->nullable()->after('currency_symbol'),
            'profit_per_unit_currency' => fn (Blueprint $table) => $table->decimal('profit_per_unit_currency', 18, 2)->nullable(),
            'profit_amount_currency' => fn (Blueprint $table) => $table->decimal('profit_amount_currency', 18, 2)->nullable(),
        ], ['currency_id', 'currency_code']);

        $this->restoreMoneyColumns('investor_withdrawals', [
            'currency_id' => fn (Blueprint $table) => $table->foreignId('currency_id')->nullable()->after('investment_id')->constrained('currencies')->nullOnDelete(),
            'currency_code' => fn (Blueprint $table) => $table->string('currency_code', 10)->nullable()->after('currency_id'),
            'currency_symbol' => fn (Blueprint $table) => $table->string('currency_symbol', 16)->nullable()->after('currency_code'),
            'exchange_rate' => fn (Blueprint $table) => $table->decimal('exchange_rate', 18, 8)->nullable()->after('currency_symbol'),
            'amount_currency' => fn (Blueprint $table) => $table->decimal('amount_currency', 18, 2)->nullable()->after('type'),
        ], ['currency_id', 'currency_code']);

        $this->restoreMoneyColumns('investor_wallets', [
            'currency_id' => fn (Blueprint $table) => $table->foreignId('currency_id')->nullable()->after('investor_id')->constrained('currencies')->nullOnDelete(),
            'capital_balance_currency' => fn (Blueprint $table) => $table->decimal('capital_balance_currency', 18, 2)->nullable(),
            'profit_balance_currency' => fn (Blueprint $table) => $table->decimal('profit_balance_currency', 18, 2)->nullable(),
        ], ['currency_id']);
    }

    public function down(): void
    {
        // Keep restored money columns; dropping them would erase historical snapshots.
    }

    /**
     * @param  array<string, callable(Blueprint): void>  $columns
     * @param  list<string>  $indexColumns
     */
    private function restoreMoneyColumns(string $table, array $columns, array $indexColumns = []): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
            foreach ($columns as $name => $define) {
                if (! Schema::hasColumn($table, $name)) {
                    $define($blueprint);
                }
            }
        });

        foreach ($indexColumns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($table, $column) {
                    $blueprint->index($column, substr($table, 0, 20).'_'.$column.'_idx');
                });
            } catch (\Throwable) {
                // Index already exists.
            }
        }
    }

    private function seedSystemCurrencies(): void
    {
        if (! Schema::hasTable('currencies')) {
            return;
        }

        $now = now();
        $rows = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'د.إ'],
        ];

        foreach ($rows as $row) {
            $exists = DB::table('currencies')->where('code', $row['code'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('currencies')->insert([
                'code' => $row['code'],
                'name' => $row['name'],
                'symbol' => $row['symbol'],
                'decimal_places' => 2,
                'rate_to_default' => 0,
                'is_base' => false,
                'status' => 'active',
                'trash' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
