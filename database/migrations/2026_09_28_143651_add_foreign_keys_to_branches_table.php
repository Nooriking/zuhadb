<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            // ⭐ manager_user_id → users.id
            $table->foreign('manager_user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            // ⭐ user_id (سازنده) → users.id
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['manager_user_id']);
            $table->dropForeign(['user_id']);
        });
    }
};