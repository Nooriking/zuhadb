<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_transactions')) {
            return;
        }

        Schema::table('customer_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_transactions', 'currency_id')) {
                $table->unsignedBigInteger('currency_id')->nullable()->after('customer_id');
            }
            if (! Schema::hasColumn('customer_transactions', 'currency_code')) {
                $table->string('currency_code', 10)->nullable()->after('currency_id');
            }
            if (! Schema::hasColumn('customer_transactions', 'amount_currency')) {
                $table->decimal('amount_currency', 18, 2)->nullable()->after('amount_afn');
            }
            if (! Schema::hasColumn('customer_transactions', 'exchange_rate_to_afn')) {
                $table->decimal('exchange_rate_to_afn', 18, 8)->nullable()->after('amount_currency');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_transactions')) {
            return;
        }

        Schema::table('customer_transactions', function (Blueprint $table) {
            $cols = array_values(array_filter([
                Schema::hasColumn('customer_transactions', 'currency_id') ? 'currency_id' : null,
                Schema::hasColumn('customer_transactions', 'currency_code') ? 'currency_code' : null,
                Schema::hasColumn('customer_transactions', 'amount_currency') ? 'amount_currency' : null,
                Schema::hasColumn('customer_transactions', 'exchange_rate_to_afn') ? 'exchange_rate_to_afn' : null,
            ]));

            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
