<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();

            /* ═══ RELATIONSHIPS ═══ */
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();

            $table->foreignId('sale_id')
                ->nullable()
                ->constrained('sales')
                ->nullOnDelete();

            $table->foreignId('sale_payment_id')
                ->nullable()
                ->constrained('sale_payments')
                ->nullOnDelete();

            /* ═══ DOCUMENT ═══ */
            $table->string('receipt_number')->unique();

            /* ═══ AMOUNTS ═══ */
            $table->decimal('amount_afn', 18, 2)->default(0);
            $table->decimal('remaining_due_afn', 18, 2)->default(0);

            /* ═══ TYPE ═══ */
            $table->enum('receipt_type', [
                'sale_invoice',
                'payment_receipt',
            ])->default('sale_invoice');

            /* ═══ MISC ═══ */
            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /* ═══ TIMESTAMPS ═══ */
            $table->timestamps();

            /* ═══ INDEXES ═══ */
            $table->index('customer_id');
            $table->index('sale_id');
            $table->index('sale_payment_id');
            $table->index('receipt_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};