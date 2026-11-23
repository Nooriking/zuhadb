<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_transactions', function (Blueprint $table) {
            $table->id();

            /* ═══ RELATIONSHIPS ═══ */
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            /* ═══ TRANSACTION DATA ═══ */
            $table->enum('type', ['sale', 'payment', 'return', 'adjustment']);

            // Positive = increase customer debt (sale)
            // Negative = decrease customer debt (payment / return)
            $table->decimal('amount_afn', 18, 2);

            // Remaining debt after this transaction
            $table->decimal('balance_after', 18, 2);

            /* ═══ REFERENCE — track transaction source ═══ */
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            /* ═══ MISC ═══ */
            $table->text('notes')->nullable();

            /* ═══ AUDIT ═══ */
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /* ═══ TIMESTAMPS ═══ */
            $table->timestamps();

            /* ═══ INDEXES ═══ */
            $table->index('customer_id');
            $table->index('type');
            $table->index('created_at');
            $table->index(['reference_type', 'reference_id']);
            $table->index(['customer_id', 'created_at']);   // ⭐ for per-customer queries
            $table->index(['customer_id', 'type']);          // ⭐ for per-customer per-type filters
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_transactions');
    }
};