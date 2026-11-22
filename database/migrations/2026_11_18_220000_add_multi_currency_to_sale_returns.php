<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sale_returns')) {
            return;
        }

        Schema::table('sale_returns', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_returns', 'return_currency_id')) {
                $table->unsignedBigInteger('return_currency_id')->nullable()->after('customer_id');
            }
            if (! Schema::hasColumn('sale_returns', 'return_currency_code')) {
                $table->string('return_currency_code', 10)->nullable()->after('return_currency_id');
            }
            if (! Schema::hasColumn('sale_returns', 'return_currency_symbol')) {
                $table->string('return_currency_symbol', 16)->nullable()->after('return_currency_code');
            }
            if (! Schema::hasColumn('sale_returns', 'return_amount_currency')) {
                $table->decimal('return_amount_currency', 18, 2)->nullable()->after('total_afn');
            }
            if (! Schema::hasColumn('sale_returns', 'exchange_rate_to_afn')) {
                $table->decimal('exchange_rate_to_afn', 18, 8)->nullable()->after('return_amount_currency');
            }
            if (! Schema::hasColumn('sale_returns', 'rate_effective_at')) {
                $table->dateTime('rate_effective_at')->nullable()->after('exchange_rate_to_afn');
            }
            if (! Schema::hasColumn('sale_returns', 'rate_source')) {
                $table->string('rate_source', 40)->nullable()->after('rate_effective_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sale_returns')) {
            return;
        }

        Schema::table('sale_returns', function (Blueprint $table) {
            $cols = array_values(array_filter([
                Schema::hasColumn('sale_returns', 'return_currency_id') ? 'return_currency_id' : null,
                Schema::hasColumn('sale_returns', 'return_currency_code') ? 'return_currency_code' : null,
                Schema::hasColumn('sale_returns', 'return_currency_symbol') ? 'return_currency_symbol' : null,
                Schema::hasColumn('sale_returns', 'return_amount_currency') ? 'return_amount_currency' : null,
                Schema::hasColumn('sale_returns', 'exchange_rate_to_afn') ? 'exchange_rate_to_afn' : null,
                Schema::hasColumn('sale_returns', 'rate_effective_at') ? 'rate_effective_at' : null,
                Schema::hasColumn('sale_returns', 'rate_source') ? 'rate_source' : null,
            ]));

            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
