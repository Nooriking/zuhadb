<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {

            /* ⭐ فقط ستون‌هایی که در Migration اصلی نیستند */

            // اگر customer_name_snapshot نیست → اضافه کن
            if (! Schema::hasColumn('sales', 'customer_name_snapshot')) {
                $table->string('customer_name_snapshot')
                    ->nullable()
                    ->after('customer_id');
            }

            // اگر customer_phone_snapshot نیست → اضافه کن
            if (! Schema::hasColumn('sales', 'customer_phone_snapshot')) {
                $table->string('customer_phone_snapshot', 50)
                    ->nullable()
                    ->after('customer_name_snapshot');
            }

            // اگر printed_count نیست → اضافه کن
            if (! Schema::hasColumn('sales', 'printed_count')) {
                $table->unsignedInteger('printed_count')
                    ->default(0)
                    ->after('status');
            }

            // اگر last_printed_at نیست → اضافه کن
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