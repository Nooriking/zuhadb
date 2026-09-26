<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();

            /* ═══ RELATIONSHIPS ═══ */
            $table->foreignId('sale_id')
                ->constrained('sales')
                ->cascadeOnDelete();

            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();

            /* ═══ AMOUNTS ═══ */
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->decimal('amount_currency', 18, 2)->default(0);
            $table->decimal('amount_afn', 18, 2);

            /* ═══ REMAINING DUE ═══ */
            $table->decimal('remaining_due_afn', 18, 2)->default(0);

            /* ═══ PAYMENT INFO ═══ */
            $table->enum('payment_method', [
                'cash', 'card', 'bank', 'mobile', 'credit', 'other',
            ])->default('cash');

            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->foreignId('received_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /* ═══ TIMESTAMPS ═══ */
            $table->timestamps();
            $table->softDeletes();

            /* ═══ INDEXES ═══ */
            $table->index('sale_id');
            $table->index('customer_id');
            $table->index('payment_method');
            $table->index('remaining_due_afn');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};