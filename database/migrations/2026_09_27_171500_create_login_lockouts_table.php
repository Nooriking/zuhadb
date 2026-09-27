<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_lockouts', function (Blueprint $table) {
            $table->id();
            $table->string('lock_key', 191)->unique();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('locked_until')->nullable()->index();
            $table->string('ip', 45)->nullable()->index();
            $table->string('login', 191)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_lockouts');
    }
};
