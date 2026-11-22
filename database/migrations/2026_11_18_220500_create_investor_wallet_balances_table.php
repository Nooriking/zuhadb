<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('investor_wallet_balances')) {
            return;
        }

        Schema::create('investor_wallet_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->string('currency_code', 10);
            $table->decimal('capital_balance_currency', 18, 2)->default(0);
            $table->decimal('capital_balance_afn', 18, 2)->default(0);
            $table->decimal('profit_balance_currency', 18, 2)->default(0);
            $table->decimal('profit_balance_afn', 18, 2)->default(0);
            $table->decimal('last_rate', 18, 8)->nullable();
            $table->dateTime('last_rate_at')->nullable();
            $table->timestamps();

            $table->unique(['investor_id', 'currency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_wallet_balances');
    }
};
