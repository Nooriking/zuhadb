<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->foreignId('variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();

            $table->integer('quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->integer('available_quantity')->default(0);

            $table->decimal('average_cost_afn', 18, 2)->default(0);

            $table->timestamp('last_movement_at')->nullable();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('trash')->default(false);

            $table->timestamps();

            // یکتا: هر (شعبه، واریانت) فقط یک رکورد
            $table->unique(['branch_id', 'variant_id']);

            $table->index('branch_id');
            $table->index('variant_id');
            $table->index('trash');
            $table->index('quantity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};