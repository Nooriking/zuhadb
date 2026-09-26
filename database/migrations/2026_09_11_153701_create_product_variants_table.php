<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();

            // Core relationships
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();

            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();

            // Variant information
            $table->string('name', 255)->nullable()->index();
            $table->string('sku', 100)->nullable()->index()
                  ->comment('Stock Keeping Unit');

            // ❌ description حذف شد — در Product Information هست

            $table->string('image', 255)->nullable()
                  ->comment('Variant image path');

            $table->integer('volume_ml')->nullable()
                  ->comment('Volume in milliliters');

            $table->string('concentration', 30)->nullable()->index()
                  ->comment('parfum, edp, edt, edc, edt_legere, body_mist, attar, oud, other');

            // Pricing
            $table->decimal('purchase_price_afn', 14, 2)->default(0)
                  ->comment('Last purchase price in AFN (source of truth)');

            // ❌ purchase_price_usd حذف شد — از afn ÷ exchange_rate محاسبه می‌شود

            $table->decimal('sale_price_afn', 14, 2)->default(0);
            $table->decimal('wholesale_price_afn', 14, 2)->default(0);
            $table->integer('wholesale_min_qty')->default(0);
            $table->decimal('profit_per_unit', 14, 2)->default(0);

            // Stock (cached)
            $table->integer('stock_quantity')->default(0);
            $table->integer('minimum_stock')->default(0);

            // Trash
            $table->boolean('trash')->default(false)->index();

            $table->timestamps();
            $table->softDeletes();

            // Composite indexes
            $table->index(['product_id', 'trash'], 'pv_product_trash_idx');
            $table->index(['concentration', 'volume_ml'], 'pv_concentration_volume_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};