<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->string('expense_number')->unique();
            $table->string('title');
            $table->string('category', 100);

            $table->foreignId('currency_id')
                ->constrained('currencies')
                ->restrictOnDelete();

            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->decimal('amount_currency', 18, 2);
            $table->decimal('amount_afn', 18, 2);

            $table->date('expense_date');
            $table->enum('payment_method', ['cash', 'card', 'bank', 'other'])->default('cash');
            $table->text('description')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('trash')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index('branch_id');
            $table->index('currency_id');
            $table->index('category');
            $table->index('expense_date');
            $table->index('trash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};