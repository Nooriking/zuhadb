<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exchange_rates')) {
            return;
        }

        DB::statement(
            'UPDATE exchange_rates SET trash = 1 WHERE rate < 1000 AND from_currency_id != to_currency_id'
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('exchange_rates')) {
            return;
        }

        // Re-activate only the rows this migration likely soft-trashed (stale low rates).
        DB::statement(
            'UPDATE exchange_rates SET trash = 0 WHERE trash = 1 AND rate < 1000 AND from_currency_id != to_currency_id'
        );
    }
};
