<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->enum('return_type', ['shop', 'customer'])
                ->default('customer')
                ->after('sale_id')
                ->comment('shop = refund + restock to shop; customer = credit against customer balance');
        });
    }

    public function down(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropColumn('return_type');
        });
    }
};
