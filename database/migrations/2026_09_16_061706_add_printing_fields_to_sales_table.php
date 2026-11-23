<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {

            /* ⭐ Only columns not already in the original migration */

            // If customer_name_snapshot is missing → add it
            if (! Schema::hasColumn('sales', 'customer_name_snapshot')) {
                $table->string('customer_name_snapshot')
                    ->nullable()
                    ->after('customer_id');
            }

            // If customer_phone_snapshot is missing → add it
            if (! Schema::hasColumn('sales', 'customer_phone_snapshot')) {
                $table->string('customer_phone_snapshot', 50)
                    ->nullable()
                    ->after('customer_name_snapshot');
            }

            // If printed_count is missing → add it
            if (! Schema::hasColumn('sales', 'printed_count')) {
                $table->unsignedInteger('printed_count')
                    ->default(0)
                    ->after('status');
            }

            // If last_printed_at is missing → add it
            if (! Schema::hasColumn('sales', 'last_printed_at')) {
                $table->timestamp('last_printed_at')
                    ->nullable()
                    ->after('printed_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $columns = [
                'customer_name_snapshot',
                'customer_phone_snapshot',
                'printed_count',
                'last_printed_at',
            ];

            foreach ($columns as $col) {
                if (Schema::hasColumn('sales', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};