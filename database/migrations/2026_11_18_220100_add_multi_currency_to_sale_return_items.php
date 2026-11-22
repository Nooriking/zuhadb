<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sale_return_items')) {
            return;
        }

        Schema::table('sale_return_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_return_items', 'return_currency_code')) {
                $table->string('return_currency_code', 10)->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('sale_return_items', 'unit_price_currency')) {
                $table->decimal('unit_price_currency', 18, 4)->nullable()->after('return_currency_code');
            }
            if (! Schema::hasColumn('sale_return_items', 'total_currency')) {
                $table->decimal('total_currency', 18, 2)->nullable()->after('unit_price_currency');
            }
            if (! Schema::hasColumn('sale_return_items', 'exchange_rate_to_afn')) {
                $table->decimal('exchange_rate_to_afn', 18, 8)->nullable()->after('total_afn');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sale_return_items')) {
            return;
        }

        Schema::table('sale_return_items', function (Blueprint $table) {
            $cols = array_values(array_filter([
                Schema::hasColumn('sale_return_items', 'return_currency_code') ? 'return_currency_code' : null,
                Schema::hasColumn('sale_return_items', 'unit_price_currency') ? 'unit_price_currency' : null,
                Schema::hasColumn('sale_return_items', 'total_currency') ? 'total_currency' : null,
                Schema::hasColumn('sale_return_items', 'exchange_rate_to_afn') ? 'exchange_rate_to_afn' : null,
            ]));

            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
