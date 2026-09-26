\<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();

            $table->string('code', 10)->unique();        // AFN, USD
            $table->string('name');                      // Afghan Afghani, US Dollar
            $table->string('symbol', 10)->nullable();    // ؋, $
            $table->boolean('is_base')->default(false);  // فقط یکی می‌تواند base باشد

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
            $table->index('is_base');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};