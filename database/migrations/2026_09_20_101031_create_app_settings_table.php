<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();

            /* ═══════════════════════════════════════════════════════
             |  COMPANY INFO
             ═══════════════════════════════════════════════════════ */

            $table->string('name');                              // Display name
            $table->string('company_name')->nullable();          // Official company name
            $table->string('company_name_local')->nullable();    // Local name

            /* ─── Logo ─── */
            $table->string('logo')->nullable();                  // Logo path

            /* ═══════════════════════════════════════════════════════
             |  CONTACT INFO
             ═══════════════════════════════════════════════════════ */

            $table->string('phone', 50)->nullable();             // Primary phone
            $table->string('phone_2', 50)->nullable();           // Secondary phone
            $table->string('email')->nullable();                 // Email
            $table->string('website')->nullable();               // Website

            /* ═══════════════════════════════════════════════════════
             |  ADDRESS
             ═══════════════════════════════════════════════════════ */

            $table->text('address')->nullable();                 // Full address

            /* ═══════════════════════════════════════════════════════
             |  SOCIAL MEDIA
             ═══════════════════════════════════════════════════════ */

            $table->string('social_facebook')->nullable();
            $table->string('social_instagram')->nullable();
            $table->string('social_whatsapp')->nullable();
            $table->string('social_telegram')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};