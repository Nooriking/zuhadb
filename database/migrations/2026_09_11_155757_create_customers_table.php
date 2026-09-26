<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            /* ═══ BASIC INFO ═══ */
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();

            /* ═══ CREDIT / DUE ═══ */
            $table->decimal('current_due_afn', 18, 2)->default(0);
            $table->decimal('credit_limit_afn', 18, 2)->default(0);
            $table->timestamp('last_sale_at')->nullable();

            /* ═══ STATUS ═══ */
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('trash')->default(false);

            /* ═══ RELATIONSHIPS ═══ */
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /* ═══ TIMESTAMPS ═══ */
            $table->timestamps();
            $table->softDeletes();

            /* ═══ INDEXES ═══ */
            $table->index('status');
            $table->index('trash');
            $table->index('name');
            $table->index('phone');
            $table->index('current_due_afn');
            $table->index('last_sale_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};