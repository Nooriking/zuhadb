<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('investment_purchase_allocations')) {
            return;
        }

        Schema::create('investment_purchase_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained('investments')->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('allocated_amount_afn', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('investment_id', 'idx_ipurch_inv');
            $table->index('purchase_id', 'idx_ipurch_purchase');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_purchase_allocations');
    }
};
