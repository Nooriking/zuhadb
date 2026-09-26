<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investor_withdrawals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('investor_id')
                  ->constrained('investors')
                  ->cascadeOnDelete();

            $table->foreignId('investment_id')
                  ->nullable()
                  ->constrained('investments')
                  ->nullOnDelete();

            $table->foreignId('currency_id')
                  ->constrained('currencies');

            $table->string('withdrawal_number', 50)
                  ->unique();

            // نوع برداشت
            $table->enum('type', ['profit', 'capital'])
                  ->default('profit');

            $table->decimal('exchange_rate', 18, 6)
                  ->default(1);

            $table->decimal('amount_currency', 18, 2)
                  ->default(0);

            $table->decimal('amount_afn', 18, 2)
                  ->default(0);

            $table->decimal('balance_before_afn', 18, 2)
                  ->default(0);

            $table->decimal('balance_after_afn', 18, 2)
                  ->default(0);

            $table->date('withdrawal_date');

            // وضعیت تأیید
            $table->enum('status', ['pending', 'approved', 'paid', 'rejected'])
                  ->default('pending');

            $table->text('notes')->nullable();

            $table->foreignId('approved_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            $table->foreignId('paid_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('paid_at')->nullable();

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('investor_id');
            $table->index('status');
            $table->index('type');
            $table->index('withdrawal_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_withdrawals');
    }
};