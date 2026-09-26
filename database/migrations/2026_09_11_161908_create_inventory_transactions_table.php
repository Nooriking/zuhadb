<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->foreignId('variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();

            $table->foreignId('batch_id')
                ->nullable()
                ->constrained('product_batches')
                ->nullOnDelete();

            $table->enum('type', [
                'purchase',        // ورود از خرید
                'sale',            // خروج از فروش
                'return',          // برگشت از مشتری
                'damage',          // خرابی/ضایعات
                'transfer_in',     // ورود از انتقال
                'transfer_out',    // خروج از انتقال
                'adjustment',      // تنظیم دستی
                'opening',         // موجودی اولیه
            ]);

            $table->integer('quantity');        // می‌تواند منفی باشد
            $table->integer('before_quantity');
            $table->integer('after_quantity');

            $table->string('reference_type')->nullable(); // Model class
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('branch_id');
            $table->index('variant_id');
            $table->index('batch_id');
            $table->index('type');
            $table->index(['reference_type', 'reference_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};