<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sale_return_items')) {
            return;
        }

        Schema::table('sale_return_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_return_items', 'cost_price_afn')) {
                $table->decimal('cost_price_afn', 18, 2)->default(0)->after('total_afn');
            }
            if (! Schema::hasColumn('sale_return_items', 'cogs_reversal_afn')) {
                $table->decimal('cogs_reversal_afn', 18, 2)->default(0)->after('cost_price_afn');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sale_return_items')) {
            return;
        }

        Schema::table('sale_return_items', function (Blueprint $table) {
            $cols = array_values(array_filter([
                Schema::hasColumn('sale_return_items', 'cogs_reversal_afn') ? 'cogs_reversal_afn' : null,
                Schema::hasColumn('sale_return_items', 'cost_price_afn') ? 'cost_price_afn' : null,
            ]));

            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
