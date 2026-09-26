<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('from_currency_id')
                ->constrained('currencies')
                ->cascadeOnDelete();

            $table->foreignId('to_currency_id')
                ->constrained('currencies')
                ->cascadeOnDelete();

            $table->decimal('rate', 18, 6);   // 1 from = rate to

            $table->timestamp('effective_at');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('trash')->default(false);

            $table->timestamps();

            $table->index(['from_currency_id', 'to_currency_id']);
            $table->index('effective_at');
            $table->index('trash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};