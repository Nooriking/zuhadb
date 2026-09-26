<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();

            /* ═══ RELATIONSHIPS ═══ */
            $table->foreignId('sale_id')
                ->constrained('sales')
                ->restrictOnDelete();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->restrictOnDelete();

            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();

            /* ═══ DOCUMENT ═══ */
            $table->string('return_number')->unique();

            /* ═══ AMOUNTS — فقط AFN ═══ */
            $table->decimal('subtotal_afn', 18, 2)->default(0);
            $table->decimal('total_afn', 18, 2)->default(0);

            /* ═══ CUSTOMER DUE IMPACT ═══ */
            $table->decimal('previous_due_afn', 18, 2)->default(0);
            $table->decimal('new_due_afn', 18, 2)->default(0);

            /* ═══ REFUND / SETTLEMENT ═══ */
            // اگر برگشتی بیشتر از قرض فعلی باشد → باید به مشتری پول برگردانده شود
            $table->decimal('refund_amount_afn', 18, 2)->default(0);
            $table->enum('refund_method', [
                'cash', 'card', 'bank', 'mobile', 'credit_note', 'other',
            ])->nullable();

            /* ═══ DETAILS ═══ */
            $table->text('reason')->nullable();
            $table->date('return_date');

            $table->enum('status', ['draft', 'completed', 'cancelled'])->default('draft');

            /* ═══ AUDIT ═══ */
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('trash')->default(false);

            /* ═══ TIMESTAMPS ═══ */
            $table->timestamps();
            $table->softDeletes();

            /* ═══ INDEXES ═══ */
            $table->index('sale_id');
            $table->index('branch_id');
            $table->index('customer_id');
            $table->index('status');
            $table->index('trash');
            $table->index('return_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_returns');
    }
};