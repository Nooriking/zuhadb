<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_prices')) {
            return;
        }

        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->enum('price_type', ['purchase', 'sale', 'wholesale'])->default('sale');
            $table->unsignedBigInteger('currency_id');
            $table->string('currency_code', 10);
            $table->decimal('amount', 18, 4);
            $table->integer('min_qty')->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->unique(
                ['product_id', 'variant_id', 'price_type', 'currency_id', 'min_qty'],
                'product_price_unique'
            );
            $table->index('product_id', 'pp_product_idx');
            $table->index('variant_id', 'pp_variant_idx');
            $table->index('currency_id', 'pp_currency_idx');

            $table->foreign('product_id', 'pp_product_fk')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');
            $table->foreign('variant_id', 'pp_variant_fk')
                ->references('id')
                ->on('product_variants')
                ->onDelete('cascade');
            $table->foreign('currency_id', 'pp_currency_fk')
                ->references('id')
                ->on('currencies');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
