<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('receipts')) {
            return;
        }

        Schema::table('receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('receipts', 'currency_id')) {
                $table->unsignedBigInteger('currency_id')->nullable()->after('sale_payment_id');
            }
            if (! Schema::hasColumn('receipts', 'currency_code')) {
                $table->string('currency_code', 10)->nullable()->after('currency_id');
            }
            if (! Schema::hasColumn('receipts', 'amount_currency')) {
                $table->decimal('amount_currency', 18, 2)->nullable()->after('amount_afn');
            }
            if (! Schema::hasColumn('receipts', 'exchange_rate_to_afn')) {
                $table->decimal('exchange_rate_to_afn', 18, 8)->nullable()->after('amount_currency');
            }
            if (! Schema::hasColumn('receipts', 'rate_effective_at')) {
                $table->dateTime('rate_effective_at')->nullable()->after('exchange_rate_to_afn');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('receipts')) {
            return;
        }

        Schema::table('receipts', function (Blueprint $table) {
            $cols = array_values(array_filter([
                Schema::hasColumn('receipts', 'currency_id') ? 'currency_id' : null,
                Schema::hasColumn('receipts', 'currency_code') ? 'currency_code' : null,
                Schema::hasColumn('receipts', 'amount_currency') ? 'amount_currency' : null,
                Schema::hasColumn('receipts', 'exchange_rate_to_afn') ? 'exchange_rate_to_afn' : null,
                Schema::hasColumn('receipts', 'rate_effective_at') ? 'rate_effective_at' : null,
            ]));

            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
