<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            // Snapshot برای فاکتور قدیمی
            $table->string('product_name_snapshot')
                  ->nullable()
                  ->after('batch_id');

            $table->string('sku_snapshot')
                  ->nullable()
                  ->after('product_name_snapshot');

            $table->string('barcode_snapshot')
                  ->nullable()
                  ->after('sku_snapshot');

            // سود سهم سرمایه‌گذار (این فروش)
            $table->decimal('investor_profit_currency', 18, 2)
                  ->default(0)
                  ->after('store_profit_afn');

            $table->decimal('investor_profit_afn', 18, 2)
                  ->default(0)
                  ->after('investor_profit_currency');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn([
                'product_name_snapshot',
                'sku_snapshot',
                'barcode_snapshot',
                'investor_profit_currency',
                'investor_profit_afn',
            ]);
        });
    }
};