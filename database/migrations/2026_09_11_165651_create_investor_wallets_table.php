<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investor_wallets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('investor_id')
                ->unique()
                ->constrained('investors')
                ->cascadeOnDelete();

            $table->foreignId('currency_id')
                ->nullable()
                ->constrained('currencies')
                ->nullOnDelete()
                ->comment('Default currency for this investor');

            // سرمایه
            $table->decimal('capital_balance_currency', 18, 2)->default(0);
            $table->decimal('capital_balance_afn', 18, 2)->default(0);

            // سود
            $table->decimal('profit_balance_currency', 18, 2)->default(0);
            $table->decimal('profit_balance_afn', 18, 2)->default(0);

            // آمار کلی
            $table->decimal('total_invested_afn', 18, 2)->default(0);
            $table->decimal('total_profit_earned_afn', 18, 2)->default(0);
            $table->decimal('total_withdrawn_afn', 18, 2)->default(0);

            $table->timestamp('last_transaction_at')->nullable();

            $table->timestamps();

            $table->index('investor_id');
            $table->index('currency_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_wallets');
    }
};