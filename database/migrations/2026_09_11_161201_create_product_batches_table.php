<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->foreignId('variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();


            $table->string('batch_code')->unique();

            $table->integer('quantity');
            $table->integer('remaining_quantity');

            $table->foreignId('currency_id')
                ->nullable()
                ->constrained('currencies')
                ->nullOnDelete();

            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->decimal('unit_cost_currency', 18, 2)->default(0);
            $table->decimal('unit_cost_afn', 18, 2)->default(0);

            $table->date('received_date');

            $table->enum('status', ['active', 'depleted', 'expired'])->default('active');

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('trash')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index('branch_id');
            $table->index('variant_id');
            $table->index('status');
            $table->index('trash');
            $table->index('received_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};