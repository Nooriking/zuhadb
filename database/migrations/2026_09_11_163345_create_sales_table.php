<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            /* ═══ RELATIONSHIPS ═══ */
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->restrictOnDelete();

            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();

            /* ═══ CUSTOMER SNAPSHOT ═══ */
            $table->string('customer_name_snapshot')->nullable();
            $table->string('customer_phone_snapshot', 50)->nullable();

            /* ═══ DOCUMENT NUMBERS ═══ */
            $table->string('invoice_number')->unique();
            $table->string('receipt_no')->nullable()->unique();

            /* ═══ AMOUNTS — فقط AFN ═══ */
            $table->decimal('subtotal_afn', 18, 2)->default(0);
            $table->decimal('discount_afn', 18, 2)->default(0);
            $table->decimal('total_afn', 18, 2)->default(0);

            /* ═══ CUSTOMER DUE FLOW ═══ */
            $table->decimal('previous_due_afn', 18, 2)->default(0);
            $table->decimal('new_sale_amount_afn', 18, 2)->default(0);
            $table->decimal('grand_total_due_afn', 18, 2)->default(0);
            $table->decimal('received_now_afn', 18, 2)->default(0);
            $table->decimal('remaining_due_afn', 18, 2)->default(0);

            /* ═══ PAYMENTS ═══ */
            $table->enum('payment_method', [
                'cash', 'card', 'bank', 'mobile', 'credit', 'other',
            ])->default('cash');

            $table->decimal('paid_afn', 18, 2)->default(0);
            $table->decimal('due_afn', 18, 2)->default(0);

            /* ═══ PROFIT ═══ */
            $table->decimal('total_cost_afn', 18, 2)->default(0);
            $table->decimal('total_profit_afn', 18, 2)->default(0);
            $table->decimal('total_investor_profit_afn', 18, 2)->default(0);
            $table->decimal('store_profit_afn', 18, 2)->default(0);

            /* ═══ DATES & STATUS ═══ */
            $table->date('sale_date');

            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->enum('status', ['draft', 'completed', 'cancelled'])->default('draft');

            /* ═══ PRINTING ═══ */
            $table->integer('printed_count')->default(0);
            $table->timestamp('last_printed_at')->nullable();

            /* ═══ MISC ═══ */
            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('trash')->default(false);

            /* ═══ TIMESTAMPS ═══ */
            $table->timestamps();
            $table->softDeletes();

            /* ═══ INDEXES ═══ */
            $table->index('branch_id');
            $table->index('customer_id');
            $table->index('payment_status');
            $table->index('payment_method');
            $table->index('status');
            $table->index('trash');
            $table->index('sale_date');
            $table->index('remaining_due_afn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};