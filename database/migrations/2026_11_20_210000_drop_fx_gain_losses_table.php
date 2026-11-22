<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('fx_gain_losses');
    }

    public function down(): void
    {
        if (Schema::hasTable('fx_gain_losses')) {
            return;
        }

        Schema::create('fx_gain_losses', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type', 50);
            $table->unsignedBigInteger('reference_id');
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->string('currency_code', 10)->nullable();
            $table->decimal('amount_currency', 18, 2)->nullable();
            $table->decimal('original_rate', 18, 8);
            $table->decimal('new_rate', 18, 8);
            $table->decimal('amount_afn', 18, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });
    }
};
