<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep the earliest investment per investor; remove the rest so unique can apply.
        $duplicates = DB::table('investments')
            ->select('investor_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('investor_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('investments')
                ->where('investor_id', $dup->investor_id)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

        try {
            Schema::table('investments', function (Blueprint $table) {
                $table->dropIndex(['investor_id']);
            });
        } catch (\Throwable) {
            // Index may already be absent or named differently.
        }

        Schema::table('investments', function (Blueprint $table) {
            $table->unique('investor_id');
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropUnique(['investor_id']);
            $table->index('investor_id');
        });
    }
};
