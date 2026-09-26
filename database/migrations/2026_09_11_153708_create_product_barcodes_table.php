<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();

            $table->string('barcode')->unique();
            $table->enum('barcode_type', ['EAN13', 'EAN8', 'UPC', 'CODE128', 'CODE39', 'QR', 'other'])
                ->default('EAN13');
            $table->boolean('is_primary')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');

            // ستون موجودی فعلی
            $table->integer('current_stock')->default(0);

            $table->timestamps();

            $table->index('variant_id');
            $table->index('barcode');
            $table->index('is_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_barcodes');
    }
};