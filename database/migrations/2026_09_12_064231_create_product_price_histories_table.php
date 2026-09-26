<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();

            $table->foreignId('currency_id')
                ->nullable()
                ->constrained('currencies')
                ->nullOnDelete();

            // قیمت خرید
            $table->decimal('old_purchase_price_currency', 18, 2)->default(0);
            $table->decimal('new_purchase_price_currency', 18, 2)->default(0);
            $table->decimal('old_purchase_price_afn', 18, 2)->default(0);
            $table->decimal('new_purchase_price_afn', 18, 2)->default(0);

            // قیمت فروش
            $table->decimal('old_sale_price_currency', 18, 2)->default(0);
            $table->decimal('new_sale_price_currency', 18, 2)->default(0);
            $table->decimal('old_sale_price_afn', 18, 2)->default(0);
            $table->decimal('new_sale_price_afn', 18, 2)->default(0);

            $table->decimal('exchange_rate', 18, 6)->default(1);

            $table->enum('change_type', ['purchase', 'sale', 'both'])->default('both');
            $table->text('reason')->nullable();

            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('variant_id');
            $table->index('currency_id');
            $table->index('change_type');
            $table->index('changed_by');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_histories');
    }
};