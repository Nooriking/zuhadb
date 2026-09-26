<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            // شرکت مالک شعبه
        ;

            // مدیر شعبه
            $table->foreignId('manager_user_id')
                  ->nullable()
             
                  ->constrained('users')
                  ->nullOnDelete();

            // چه کسی شعبه را ساخت
            $table->foreignId('user_id')
                  ->nullable()
                  ->after('manager_user_id')
                  ->constrained('users')
                  ->nullOnDelete();

            // Indexes
           
            $table->index('manager_user_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
       
            $table->dropForeign(['manager_user_id']);
            $table->dropForeign(['user_id']);
            
            $table->dropIndex(['manager_user_id']);
            $table->dropIndex(['user_id']);
            $table->dropColumn(['manager_user_id', 'user_id']);
        });
    }
};