<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investors', function (Blueprint $table) {
            $table->id();

            // ✅ user_id با unique — یک user نمی‌تواند چند investor فعال داشته باشد
            $table->foreignId('user_id')
                ->nullable()
                ->unique('investors_user_id_unique')   // ✅ جلوگیری از تکرار
                ->constrained('users')
                ->nullOnDelete()
                ->comment('If the investor also has a system login account. Unique: one user = one investor.');

            $table->string('full_name');
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();

            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('trash')->default(false);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // ❌ index('user_id') لازم نیست چون unique خودش index می‌سازد
            // $table->index('user_id');   ← حذف شد

            $table->index('status');
            $table->index('trash');
            $table->index('full_name');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investors');
    }
};