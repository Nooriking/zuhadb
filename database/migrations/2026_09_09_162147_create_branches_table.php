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

            // ⭐ Manager is optional — FK added later in a separate migration
            // because the users table is not created yet
            $table->unsignedBigInteger('manager_user_id')->nullable();

            // ⭐ Branch creator (Super Admin) — FK added later
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