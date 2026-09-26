<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'exchange_rate')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('exchange_rate', 12, 4)->nullable()->after('purchase_date');
            });
        }

        if (! Schema::hasTable('exchange_rate_history')) {
            Schema::create('exchange_rate_history', function (Blueprint $table) {
                $table->id();
                $table->date('rate_date')->unique();
                $table->decimal('usd_to_afn', 15, 4);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'exchange_rate')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('exchange_rate');
            });
        }
    }
};
