<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expenses originally had currency_id / amount_currency / exchange_rate, then lost them
 * when currencies were temporarily removed. Restore immutable FX snapshots so AFN ledger
 * history stays correct when expenses are recorded in USD/AED.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expenses')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'currency_id')) {
                $table->unsignedBigInteger('currency_id')->nullable()->after('category');
            }
            if (! Schema::hasColumn('expenses', 'currency_code')) {
                $table->string('currency_code', 10)->nullable()->after('currency_id');
            }
            if (! Schema::hasColumn('expenses', 'currency_symbol')) {
                $table->string('currency_symbol', 16)->nullable()->after('currency_code');
            }
            if (! Schema::hasColumn('expenses', 'amount_currency')) {
                $table->decimal('amount_currency', 18, 2)->nullable()->after('currency_symbol');
            }
            if (! Schema::hasColumn('expenses', 'exchange_rate')) {
                $table->decimal('exchange_rate', 18, 8)->nullable()->after('amount_currency');
            }
            if (! Schema::hasColumn('expenses', 'rate_effective_at')) {
                $table->dateTime('rate_effective_at')->nullable()->after('exchange_rate');
            }
        });

        $afn = DB::table('currencies')->where('code', 'AFN')->whereNull('deleted_at')->first();

        if ($afn) {
            DB::table('expenses')
                ->whereNull('currency_id')
                ->update([
                    'currency_id' => $afn->id,
                    'currency_code' => 'AFN',
                    'currency_symbol' => $afn->symbol,
                    'amount_currency' => DB::raw('amount_afn'),
                    'exchange_rate' => 1,
                    'rate_effective_at' => DB::raw('COALESCE(expense_date, created_at, NOW())'),
                ]);
        } else {
            DB::table('expenses')
                ->whereNull('currency_code')
                ->update([
                    'currency_code' => 'AFN',
                    'amount_currency' => DB::raw('amount_afn'),
                    'exchange_rate' => 1,
                    'rate_effective_at' => DB::raw('COALESCE(expense_date, created_at, NOW())'),
                ]);
        }

        if (Schema::hasColumn('expenses', 'currency_id') && Schema::hasTable('currencies')) {
            $fkExists = collect(DB::select(
                'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME = ?',
                [DB::getDatabaseName(), 'expenses', 'currency_id', 'currencies']
            ))->isNotEmpty();

            if (! $fkExists) {
                Schema::table('expenses', function (Blueprint $table) {
                    $table->foreign('currency_id')
                        ->references('id')
                        ->on('currencies')
                        ->restrictOnDelete();
                });
            }
        }

        try {
            Schema::table('expenses', function (Blueprint $table) {
                $table->index('currency_id');
                $table->index('currency_code');
            });
        } catch (\Throwable) {
            // Indexes may already exist on some environments.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('expenses')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'currency_id')) {
                try {
                    $table->dropForeign(['currency_id']);
                } catch (\Throwable) {
                    //
                }
            }

            $cols = array_values(array_filter([
                Schema::hasColumn('expenses', 'currency_id') ? 'currency_id' : null,
                Schema::hasColumn('expenses', 'currency_code') ? 'currency_code' : null,
                Schema::hasColumn('expenses', 'currency_symbol') ? 'currency_symbol' : null,
                Schema::hasColumn('expenses', 'amount_currency') ? 'amount_currency' : null,
                Schema::hasColumn('expenses', 'exchange_rate') ? 'exchange_rate' : null,
                Schema::hasColumn('expenses', 'rate_effective_at') ? 'rate_effective_at' : null,
            ]));

            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
