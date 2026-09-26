<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('contact_person')->nullable();
            $table->text('address')->nullable();

            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->foreignId('opening_balance_currency_id')
                ->nullable()
                ->constrained('currencies')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('trash')->default(false);

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('trash');
            $table->index('user_id');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};