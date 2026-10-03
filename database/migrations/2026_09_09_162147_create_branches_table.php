<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();

            // ⭐ Manager اختیاری — FK بعداً در migration جداگانه اضافه می‌شود
            // چون جدول users هنوز ساخته نشده
            $table->unsignedBigInteger('manager_user_id')->nullable();

            // ⭐ سازنده شعبه (Super Admin) — FK بعداً
            $table->unsignedBigInteger('user_id')->nullable();

            // Basic info
            $table->string('name');
            $table->string('code')->unique();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();

            // Location
            $table->string('country')->nullable();
            $table->string('province')->nullable();
            $table->string('district')->nullable();
            $table->text('address')->nullable();

            // Status & trash
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('trash')->default(false);

            $table->timestamps();

            // Indexes
            $table->index('trash');
            $table->index('status');
            $table->index('manager_user_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};