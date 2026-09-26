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

            $table->string('name');                              // نام نمایشی
            $table->string('company_name')->nullable();          // نام رسمی شرکت
            $table->string('company_name_local')->nullable();    // نام محلی

            /* ─── لوگو ─── */
            $table->string('logo')->nullable();                  // مسیر لوگو

            /* ═══════════════════════════════════════════════════════
             |  CONTACT INFO
             ═══════════════════════════════════════════════════════ */

            $table->string('phone', 50)->nullable();             // شماره تماس اصلی
            $table->string('phone_2', 50)->nullable();           // شماره تماس دوم
            $table->string('email')->nullable();                 // ایمیل
            $table->string('website')->nullable();               // وبسایت

            /* ═══════════════════════════════════════════════════════
             |  ADDRESS
             ═══════════════════════════════════════════════════════ */

            $table->text('address')->nullable();                 // آدرس کامل

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