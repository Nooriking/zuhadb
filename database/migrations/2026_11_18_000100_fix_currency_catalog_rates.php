<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('currencies')) {
            return;
        }

        $now = now();

        DB::table('currencies')->where('code', 'AFN')->update([
            'rate_to_default' => '1.00000000',
            'is_base' => true,
            'updated_at' => $now,
        ]);

        DB::table('currencies')->where('code', 'USD')->update([
            'rate_to_default' => '56000.00000000',
            'is_base' => false,
            'updated_at' => $now,
        ]);

        DB::table('currencies')->where('code', 'AED')->update([
            'rate_to_default' => '15250.00000000',
            'is_base' => false,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('currencies')) {
            return;
        }

        // Do not invent previous operational rates; leave catalog untouched on rollback.
    }
};
