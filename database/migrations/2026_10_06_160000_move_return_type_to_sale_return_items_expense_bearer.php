<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->enum('expense_bearer', ['shop', 'customer'])
                ->default('customer')
                ->after('expense_amount_afn')
                ->comment('shop = item expense recorded as office expense; customer = deducted from customer return credit');
        });

        if (Schema::hasColumn('sale_returns', 'return_type')) {
            Schema::table('sale_returns', function (Blueprint $table) {
                $table->dropColumn('return_type');
            });
        }
    }

    public function down(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->enum('return_type', ['shop', 'customer'])
                ->default('customer')
                ->after('sale_id');
        });

        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->dropColumn('expense_bearer');
        });
    }
};
