<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (! Schema::hasColumn('sales', 'ledger_currency_code')) {
                    $table->string('ledger_currency_code', 10)->default('AFN')->after('total_afn');
                }
                if (! Schema::hasColumn('sales', 'ledger_currency_symbol')) {
                    $table->string('ledger_currency_symbol', 16)->nullable()->after('ledger_currency_code');
                }
                if (! Schema::hasColumn('sales', 'ledger_decimal_places')) {
                    $table->unsignedTinyInteger('ledger_decimal_places')->default(2)->after('ledger_currency_symbol');
                }
            });
        }

        if (Schema::hasTable('sale_payments')) {
            Schema::table('sale_payments', function (Blueprint $table) {
                if (! Schema::hasColumn('sale_payments', 'ledger_currency_code')) {
                    $table->string('ledger_currency_code', 10)->default('AFN')->after('amount_afn');
                }
                if (! Schema::hasColumn('sale_payments', 'ledger_currency_symbol')) {
                    $table->string('ledger_currency_symbol', 16)->nullable()->after('ledger_currency_code');
                }
                if (! Schema::hasColumn('sale_payments', 'ledger_decimal_places')) {
                    $table->unsignedTinyInteger('ledger_decimal_places')->default(2)->after('ledger_currency_symbol');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                $cols = array_values(array_filter([
                    Schema::hasColumn('sales', 'ledger_currency_code') ? 'ledger_currency_code' : null,
                    Schema::hasColumn('sales', 'ledger_currency_symbol') ? 'ledger_currency_symbol' : null,
                    Schema::hasColumn('sales', 'ledger_decimal_places') ? 'ledger_decimal_places' : null,
                ]));
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('sale_payments')) {
            Schema::table('sale_payments', function (Blueprint $table) {
                $cols = array_values(array_filter([
                    Schema::hasColumn('sale_payments', 'ledger_currency_code') ? 'ledger_currency_code' : null,
                    Schema::hasColumn('sale_payments', 'ledger_currency_symbol') ? 'ledger_currency_symbol' : null,
                    Schema::hasColumn('sale_payments', 'ledger_decimal_places') ? 'ledger_decimal_places' : null,
                ]));
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
