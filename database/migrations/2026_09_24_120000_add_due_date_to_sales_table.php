<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sales', 'due_date')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('remaining_due_afn');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales', 'due_date')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('due_date');
            });
        }
    }
};
