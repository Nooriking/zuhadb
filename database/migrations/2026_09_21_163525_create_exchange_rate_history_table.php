<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rate_history', function (Blueprint $table) {
            $table->id();

            /* ═══ RATE DATA ═══ */
            $table->date('rate_date')->unique();
            $table->decimal('usd_to_afn', 15, 4);

            /* ═══ MISC ═══ */
            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /* ═══ TIMESTAMPS ═══ */
            $table->timestamps();

            /* ═══ INDEXES ═══ */
            $table->index('rate_date');
            $table->index('usd_to_afn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rate_history');
    }
};