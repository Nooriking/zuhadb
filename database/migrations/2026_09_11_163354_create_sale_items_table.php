<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sale_id')
                ->constrained('sales')
                ->cascadeOnDelete();

            $table->foreignId('variant_id')
                ->constrained('product_variants')
                ->restrictOnDelete();

            $table->foreignId('batch_id')
                ->nullable()
                ->constrained('product_batches')
                ->nullOnDelete();

            $table->integer('quantity');

            $table->decimal('cost_price_currency', 18, 2)->default(0);
            $table->decimal('cost_price_afn', 18, 2)->default(0);

            $table->decimal('sale_price_currency', 18, 2)->default(0);
            $table->decimal('sale_price_afn', 18, 2)->default(0);

            $table->decimal('discount_currency', 18, 2)->default(0);
            $table->decimal('discount_afn', 18, 2)->default(0);

            $table->decimal('line_total_currency', 18, 2)->default(0);
            $table->decimal('line_total_afn', 18, 2)->default(0);

            $table->decimal('store_profit_currency', 18, 2)->default(0);
            $table->decimal('store_profit_afn', 18, 2)->default(0);

            $table->timestamps();

            $table->index('sale_id');
            $table->index('variant_id');
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};