<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep the earliest branch assignment when a user manages multiple branches.
        $duplicates = DB::table('branches')
            ->select('manager_user_id', DB::raw('MIN(id) as keep_id'))
            ->whereNotNull('manager_user_id')
            ->groupBy('manager_user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('branches')
                ->where('manager_user_id', $dup->manager_user_id)
                ->where('id', '!=', $dup->keep_id)
                ->update(['manager_user_id' => null]);
        }

        // MySQL: cannot drop an index that backs a FK — drop FK, replace index with unique, re-add FK.
        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['manager_user_id']);
        });

        try {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropIndex(['manager_user_id']);
            });
        } catch (\Throwable) {
            // Index may already be gone with the FK.
        }

        Schema::table('branches', function (Blueprint $table) {
            $table->unique('manager_user_id');
            $table->foreign('manager_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['manager_user_id']);
            $table->dropUnique(['manager_user_id']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->index('manager_user_id');
            $table->foreign('manager_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }
};
