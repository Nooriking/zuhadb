<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot customer AR by currency onto each sale so receipts keep the
 * original owed units (e.g. 700 USD) instead of re-converting via this sale's FX.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'previous_due_by_currency')) {
                $table->json('previous_due_by_currency')->nullable()->after('previous_due_afn');
            }
            if (! Schema::hasColumn('sales', 'remaining_due_by_currency')) {
                $table->json('remaining_due_by_currency')->nullable()->after('remaining_due_afn');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'remaining_due_by_currency')) {
                $table->dropColumn('remaining_due_by_currency');
            }
            if (Schema::hasColumn('sales', 'previous_due_by_currency')) {
                $table->dropColumn('previous_due_by_currency');
            }
        });
    }
};
