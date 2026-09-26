<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_batches', function (Blueprint $table) {
            // Which investor owns this batch
            $table->foreignId('investor_id')
                  ->nullable()
                  ->constrained('investors')
                  ->nullOnDelete();

            // Total cost of this batch
            $table->decimal('total_cost_currency', 18, 2)
                  ->default(0);

            $table->decimal('total_cost_afn', 18, 2)
                  ->default(0);

            // Investor profit share per unit
            $table->decimal('profit_per_unit_currency', 18, 2)
                  ->default(0);

            $table->decimal('profit_per_unit_afn', 18, 2)
                  ->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('product_batches', function (Blueprint $table) {
            $table->dropForeign(['investor_id']);
            $table->dropColumn([
                'investor_id',
                'total_cost_currency',
                'total_cost_afn',
                'profit_per_unit_currency',
                'profit_per_unit_afn',
            ]);
        });
    }
};