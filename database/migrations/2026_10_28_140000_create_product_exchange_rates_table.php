<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_exchange_rates')) {
            return;
        }

        Schema::create('product_exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('from_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignId('to_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('rate', 18, 8);
            $table->timestamps();

            $table->unique(['product_id', 'from_currency_id', 'to_currency_id'], 'product_fx_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_exchange_rates');
    }
};
