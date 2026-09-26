<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();

            /* ═══ RELATIONSHIPS ═══ */
            $table->foreignId('sale_return_id')
                ->constrained('sale_returns')
                ->cascadeOnDelete();

            $table->foreignId('sale_item_id')
                ->constrained('sale_items')
                ->restrictOnDelete();

            $table->foreignId('batch_id')
                ->nullable()
                ->constrained('product_batches')
                ->nullOnDelete();

            $table->foreignId('variant_id')
                ->constrained('product_variants')
                ->restrictOnDelete();

            /* ═══ QUANTITY ═══ */
            $table->integer('quantity');

            /* ═══ PRICES — فقط AFN ═══ */
            $table->decimal('unit_price_afn', 18, 2)->default(0);
            $table->decimal('total_afn', 18, 2)->default(0);

            /* ═══ CONDITION — وضعیت کالا ═══ */
            $table->enum('condition', ['good', 'damaged', 'expired', 'other'])
                ->default('good');

            /* ═══ REASON ═══ */
            $table->text('reason')->nullable();

            /* ═══ TIMESTAMPS ═══ */
            $table->timestamps();

            /* ═══ INDEXES ═══ */
            $table->index('sale_return_id');
            $table->index('sale_item_id');
            $table->index('batch_id');
            $table->index('variant_id');
            $table->index('condition');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
    }
};