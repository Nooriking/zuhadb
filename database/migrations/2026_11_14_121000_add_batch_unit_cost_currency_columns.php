<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_batches')) {
            return;
        }

        Schema::table('product_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('product_batches', 'unit_cost_currency')) {
                $table->decimal('unit_cost_currency', 18, 4)->nullable()->after('remaining_quantity');
            }
            if (! Schema::hasColumn('product_batches', 'exchange_rate')) {
                $table->decimal('exchange_rate', 18, 8)->nullable()->after('unit_cost_currency');
            }
        });
    }

    public function down(): void
    {
        // Keep financial columns.
    }
};
