<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════
        // SALE_ITEMS: cost_breakdown (JSON)
        // ═══════════════════════════════════════════════════════
        Schema::table('sale_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_items', 'cost_breakdown')) {
                $table->json('cost_breakdown')
                    ->nullable()
                    ->after('investor_profit_afn');
            }
        });

        // ═══════════════════════════════════════════════════════
        // SALES: snapshot + print columns
        // ═══════════════════════════════════════════════════════
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'customer_name_snapshot')) {
                $table->string('customer_name_snapshot')->nullable()->after('customer_id');
            }
            if (! Schema::hasColumn('sales', 'customer_phone_snapshot')) {
                $table->string('customer_phone_snapshot', 50)->nullable()->after('customer_name_snapshot');
            }
            if (! Schema::hasColumn('sales', 'printed_count')) {
                $table->integer('printed_count')->default(0)->after('status');
            }
            if (! Schema::hasColumn('sales', 'last_printed_at')) {
                $table->timestamp('last_printed_at')->nullable()->after('printed_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            if (Schema::hasColumn('sale_items', 'cost_breakdown')) {
                $table->dropColumn('cost_breakdown');
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            $columns = ['customer_name_snapshot', 'customer_phone_snapshot', 'printed_count', 'last_printed_at'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('sales', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};