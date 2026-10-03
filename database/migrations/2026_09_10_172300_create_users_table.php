<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────
        // 1. users
        // ─────────────────────────────────────────
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // رابطه با شعبه (شعبه قبلاً ساخته شده)
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();

            // اطلاعات شخصی
            $table->string('name');
            $table->string('username')->nullable()->unique();
            $table->string('email')->nullable()->unique();
            $table->string('phone', 50)->nullable();

            // احراز هویت
            $table->string('password');

            // نقش — ثابت (enum)
            $table->enum('role', [
                'super_admin',
                'admin',
                'branch_manager',
                'cashier',
                'accountant',
                'investor',
                'salesperson',
            ])->default('salesperson');

            // آخرین ورود
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();

            // وضعیت
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');

            // soft delete (اپلیکیشن)
            $table->boolean('trash')->default(false);

            // ردیابی — چه کسی ساخت/آخرین ویرایش کرد
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();   // deleted_at

            // ایندکس‌ها
            $table->index('branch_id');
            $table->index('role');
            $table->index('status');
            $table->index('trash');
            $table->index('user_id');
        });

        // ─────────────────────────────────────────
        // 2. password_reset_tokens
        // ─────────────────────────────────────────
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // ─────────────────────────────────────────
        // 3. sessions
        // ─────────────────────────────────────────
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};