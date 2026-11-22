<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persist the FX board used when the batch cost was valued (historical snapshot).
     */
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

        Schema::table('product_batches', function (Blueprint $table) {
            foreach (['usd_to_afn_rate', 'aed_to_afn_rate', 'usd_to_aed_rate', 'rate_effective_at', 'rate_source'] as $column) {
                if (Schema::hasColumn('product_batches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
