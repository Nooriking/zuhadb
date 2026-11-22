<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_balances')) {
            return;
        }

        Schema::create('customer_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->string('currency_code', 10);
            $table->decimal('balance_currency', 18, 2)->default(0);
            $table->decimal('balance_afn', 18, 2)->default(0);
            $table->decimal('last_rate', 18, 8)->nullable();
            $table->dateTime('last_rate_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'currency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_balances');
    }
};
