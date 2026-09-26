<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stock_transfer_id')
                ->constrained('stock_transfers')
                ->cascadeOnDelete();

            $table->foreignId('from_batch_id')
                ->constrained('product_batches')
                ->restrictOnDelete();

            $table->foreignId('to_batch_id')
                ->nullable()
                ->constrained('product_batches')
                ->nullOnDelete();

            $table->foreignId('variant_id')
                ->constrained('product_variants')
                ->restrictOnDelete();

            $table->integer('quantity');

            $table->timestamps();

            $table->index('stock_transfer_id');
            $table->index('from_batch_id');
            $table->index('to_batch_id');
            $table->index('variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
    }
};