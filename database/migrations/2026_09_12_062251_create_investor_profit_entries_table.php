<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investor_profit_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('investor_id')
                ->constrained('investors')
                ->cascadeOnDelete();

            $table->foreignId('investment_id')
                ->nullable()
                ->constrained('investments')
                ->nullOnDelete();

            $table->foreignId('investment_product_allocation_id')
                ->nullable()
                ->constrained('investment_product_allocations')
                ->nullOnDelete();

            $table->foreignId('sale_id')
                ->nullable()
                ->constrained('sales')
                ->nullOnDelete();

            $table->foreignId('sale_item_id')
                ->nullable()
                ->constrained('sale_items')
                ->nullOnDelete();

            $table->foreignId('currency_id')
                ->nullable()
                ->constrained('currencies')
                ->nullOnDelete();

            $table->decimal('exchange_rate', 18, 6)->default(1);

            $table->integer('quantity');

            $table->decimal('profit_per_unit_currency', 18, 2)->default(0);
            $table->decimal('profit_per_unit_afn', 18, 2)->default(0);

            $table->decimal('profit_amount_currency', 18, 2)->default(0);
            $table->decimal('profit_amount_afn', 18, 2)->default(0);

            $table->enum('entry_type', ['profit', 'loss', 'adjustment'])->default('profit');
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');

            $table->timestamp('earned_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->foreignId('paid_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('investor_id');
            $table->index('investment_id');
            $table->index('investment_product_allocation_id');
            $table->index('sale_id');
            $table->index('sale_item_id');
            $table->index('status');
            $table->index('entry_type');
            $table->index('earned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_profit_entries');
    }
};