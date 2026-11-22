<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable FX board snapshot on each batch (source of truth for unit_cost_afn).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_batches')) {
            return;
        }

        Schema::table('product_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('product_batches', 'usd_to_afn_rate')) {
                $table->decimal('usd_to_afn_rate', 18, 8)->nullable()->after('exchange_rate');
            }
            if (! Schema::hasColumn('product_batches', 'aed_to_afn_rate')) {
                $table->decimal('aed_to_afn_rate', 18, 8)->nullable()->after('usd_to_afn_rate');
            }
            if (! Schema::hasColumn('product_batches', 'usd_to_aed_rate')) {
                $table->decimal('usd_to_aed_rate', 18, 8)->nullable()->after('aed_to_afn_rate');
            }
            if (! Schema::hasColumn('product_batches', 'rate_effective_at')) {
                $table->dateTime('rate_effective_at')->nullable()->after('usd_to_aed_rate');
            }
            if (! Schema::hasColumn('product_batches', 'rate_source')) {
                $table->string('rate_source', 40)->nullable()->after('rate_effective_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_batches')) {
            return;
        }

        $cols = array_values(array_filter(
            ['usd_to_afn_rate', 'aed_to_afn_rate', 'usd_to_aed_rate', 'rate_effective_at', 'rate_source'],
            fn (string $col) => Schema::hasColumn('product_batches', $col)
        ));

        if ($cols === []) {
            return;
        }

        Schema::table('product_batches', function (Blueprint $table) use ($cols) {
            $table->dropColumn($cols);
        });
    }
};
