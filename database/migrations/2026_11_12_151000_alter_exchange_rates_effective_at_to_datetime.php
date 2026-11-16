<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exchange_rates')) {
            return;
        }

        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->dateTime('effective_at')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('exchange_rates')) {
            return;
        }

        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->timestamp('effective_at')->change();
        });
    }
};
