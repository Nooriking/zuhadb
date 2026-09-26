<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investor_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('investor_id')
                ->constrained('investors')
                ->cascadeOnDelete();

            $table->foreignId('investment_id')
                ->nullable()
                ->constrained('investments')
                ->nullOnDelete();

            $table->foreignId('currency_id')
                ->nullable()
                ->constrained('currencies')
                ->nullOnDelete();

            $table->decimal('exchange_rate', 18, 6)->default(1);

            $table->enum('type', [
                'capital_deposit',    // واریز سرمایه
                'capital_withdrawn',  // برداشت سرمایه
                'profit_earned',      // کسب سود
                'profit_withdrawn',   // برداشت سود
                'reinvestment',       // سرمایه‌گذاری مجدد
                'adjustment',         // تنظیم دستی
                'loss',               // ضرر
            ]);

            $table->decimal('amount_currency', 18, 2);
            $table->decimal('amount_afn', 18, 2);

            $table->enum('balance_type', ['capital', 'profit'])->default('capital');

            $table->decimal('balance_before_afn', 18, 2)->default(0);
            $table->decimal('balance_after_afn', 18, 2)->default(0);

            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->text('description')->nullable();
            $table->date('transaction_date');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('investor_id');
            $table->index('investment_id');
            $table->index('currency_id');
            $table->index('type');
            $table->index('transaction_date');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_transactions');
    }
};