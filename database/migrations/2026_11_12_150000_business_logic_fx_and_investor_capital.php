<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exchange_rates') && Schema::hasTable('currencies')) {
            Schema::create('exchange_rates', function (Blueprint $table) {
                $table->id();

                $table->foreignId('from_currency_id')
                    ->constrained('currencies')
                    ->cascadeOnDelete();

                $table->foreignId('to_currency_id')
                    ->constrained('currencies')
                    ->cascadeOnDelete();

                $table->decimal('rate', 18, 8);
                $table->dateTime('effective_at');
                $table->string('source', 40)->default('manual');

                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->boolean('trash')->default(false);
                $table->timestamps();

                $table->index(['from_currency_id', 'to_currency_id', 'effective_at'], 'exchange_rates_pair_effective_idx');
                $table->index('trash');
            });
        } elseif (Schema::hasTable('exchange_rates') && ! Schema::hasColumn('exchange_rates', 'source')) {
            Schema::table('exchange_rates', function (Blueprint $table) {
                $table->string('source', 40)->default('manual')->after('effective_at');
            });
        }

        if (Schema::hasTable('investments')) {
            try {
                Schema::table('investments', function (Blueprint $table) {
                    $table->dropUnique(['investor_id']);
                });
            } catch (\Throwable) {
                try {
                    DB::statement('ALTER TABLE investments DROP INDEX investments_investor_id_unique');
                } catch (\Throwable) {
                    // Index may already be absent.
                }
            }
        }

        if (Schema::hasTable('investment_product_allocations')
            && ! Schema::hasColumn('investment_product_allocations', 'investment_purchase_allocation_id')) {
            Schema::table('investment_product_allocations', function (Blueprint $table) {
                $table->unsignedBigInteger('investment_purchase_allocation_id')->nullable()->after('investment_id');
                $table->foreign('investment_purchase_allocation_id', 'ipa_purchase_alloc_fk')
                    ->references('id')
                    ->on('investment_purchase_allocations')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('investor_transactions')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE investor_transactions MODIFY COLUMN type ENUM(
                    'capital_deposit',
                    'goods_deposit',
                    'capital_withdrawn',
                    'profit_earned',
                    'profit_withdrawn',
                    'reinvestment',
                    'adjustment',
                    'loss'
                ) NOT NULL");
            }
        }

        if (Schema::hasTable('investor_profit_entries')) {
            try {
                Schema::table('investor_profit_entries', function (Blueprint $table) {
                    $table->unique(
                        ['sale_item_id', 'investment_product_allocation_id', 'entry_type'],
                        'investor_profit_entries_sale_item_alloc_unique'
                    );
                });
            } catch (\Throwable) {
                // Skip when duplicates exist or the unique already exists.
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('investment_product_allocations')
            && Schema::hasColumn('investment_product_allocations', 'investment_purchase_allocation_id')) {
            Schema::table('investment_product_allocations', function (Blueprint $table) {
                try {
                    $table->dropForeign('ipa_purchase_alloc_fk');
                } catch (\Throwable) {
                    //
                }
                $table->dropColumn('investment_purchase_allocation_id');
            });
        }

        if (Schema::hasTable('investments')) {
            Schema::table('investments', function (Blueprint $table) {
                $table->unique('investor_id');
            });
        }

        if (Schema::hasTable('investor_transactions')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE investor_transactions MODIFY COLUMN type ENUM(
                    'capital_deposit',
                    'capital_withdrawn',
                    'profit_earned',
                    'profit_withdrawn',
                    'reinvestment',
                    'adjustment',
                    'loss'
                ) NOT NULL");
            }
        }

        Schema::dropIfExists('exchange_rates');
    }
};
