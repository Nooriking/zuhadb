<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('from_branch_id')
                ->constrained('branches')
                ->restrictOnDelete();

            $table->foreignId('to_branch_id')
                ->constrained('branches')
                ->restrictOnDelete();

            $table->string('transfer_number')->unique();
            $table->date('transfer_date');

            $table->enum('status', ['draft', 'in_transit', 'completed', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('trash')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index('from_branch_id');
            $table->index('to_branch_id');
            $table->index('status');
            $table->index('trash');
            $table->index('transfer_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};