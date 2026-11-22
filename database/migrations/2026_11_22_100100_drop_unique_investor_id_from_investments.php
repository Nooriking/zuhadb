<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One investor may have many investments (auto-created per purchase).
 */
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::getDatabaseName();
        $indexes = DB::select(
            'SELECT INDEX_NAME, NON_UNIQUE FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$db, 'investments', 'investor_id']
        );

        foreach ($indexes as $index) {
            if ((int) $index->NON_UNIQUE === 0 && $index->INDEX_NAME !== 'PRIMARY') {
                Schema::table('investments', function (Blueprint $table) use ($index) {
                    $table->dropUnique($index->INDEX_NAME);
                });
            }
        }

        // Ensure a non-unique index remains for lookups.
        $hasIndex = collect($indexes)->contains(fn ($i) => (int) $i->NON_UNIQUE === 1);
        if (! $hasIndex) {
            Schema::table('investments', function (Blueprint $table) {
                $table->index('investor_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->unique('investor_id');
        });
    }
};
