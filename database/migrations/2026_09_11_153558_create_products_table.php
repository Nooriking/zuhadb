<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // ============================================================
            //  PRODUCT INFORMATION
            // ============================================================

            $table->foreignId('brand_id')
                  ->nullable()
                  ->constrained('brands')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();

            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();

            $table->string('name', 255)->index();
            $table->string('gender', 20)->default('unisex')
                  ->comment('male, female, unisex');
            $table->string('status', 20)->default('active')->index()
                  ->comment('active, inactive');

            $table->text('description')->nullable();

            // ❌ image حذف شد

            // ============================================================
            //  PURCHASE INFORMATION
            // ============================================================

            $table->unsignedBigInteger('variant_id')
                  ->nullable()
                  ->index()
                  ->comment('FK added later via add_variant_fk_to_products');

            $table->unsignedBigInteger('investor_id')
                  ->nullable()
                  ->index()
                  ->comment('FK added later via add_investor_fk_to_products');

            $table->unsignedBigInteger('supplier_id')
                  ->nullable()
                  ->index()
                  ->comment('FK added later via add_supplier_fk_to_products');

            // ❌ currency_id حذف شد
            // ❌ purchase_number حذف شد
            // ❌ unit_cost حذف شد

            $table->string('invoice_number', 100)->nullable()->index();
            $table->date('purchase_date')->nullable()->index();

            // Quantities
            $table->integer('quantity')->default(1);
            $table->integer('remaining_quantity')->default(0)
                  ->comment('Remaining quantity in this batch');

            // Prices
            $table->decimal('purchase_price_afn', 14, 2)->default(0)
                  ->comment('Total purchase price in AFN = variant.purchase_price_afn × quantity');

            $table->decimal('purchase_price_usd', 14, 2)->default(0)
                  ->comment('Total purchase price in USD = purchase_price_afn ÷ exchange_rate');

            $table->decimal('exchange_rate', 12, 6)->nullable()
                  ->comment('USD to AFN exchange rate');

            // Wholesale pricing (snapshot از variant)
            $table->decimal('wholesale_price_afn', 14, 2)->default(0);
            $table->integer('wholesale_min_qty')->default(0);

            // Profit
            $table->decimal('profit_per_unit', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0)
                  ->comment('= purchase_price_afn × quantity (line total cost)');
            $table->decimal('line_profit', 14, 2)->default(0)
                  ->comment('= profit_per_unit × quantity (total profit)');

            // Bill & notes
            $table->string('bill_image', 255)->nullable()
                  ->comment('Purchase bill image');

            $table->text('notes')->nullable();

            // Payment status
            $table->string('payment_status', 50)->nullable()->index()
                  ->comment('paid, unpaid, partial');

            // ============================================================
            //  SOFT DELETE / TRASH
            // ============================================================

            $table->boolean('trash')->default(false)->index()
                  ->comment('Manual soft-delete flag');

            // ============================================================
            //  TIMESTAMPS
            // ============================================================

            $table->timestamps();
            $table->softDeletes();

            // ============================================================
            //  COMPOSITE INDEXES
            // ============================================================

            $table->index(['brand_id', 'status'], 'products_brand_status_idx');
            $table->index(['status', 'trash'], 'products_status_trash_idx');
            $table->index(['gender', 'status'], 'products_gender_status_idx');
            $table->index(['variant_id', 'purchase_date'], 'products_variant_date_idx');
            $table->index(['investor_id', 'purchase_date'], 'products_investor_date_idx');
            $table->index(['supplier_id', 'purchase_date'], 'products_supplier_date_idx');
            $table->index(['payment_status', 'purchase_date'], 'products_payment_date_idx');
            $table->index(['purchase_date', 'quantity'], 'products_date_qty_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};