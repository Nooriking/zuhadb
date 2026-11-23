<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_batches')) {
            return;
        }

        Schema::table('product_batches', function (Blueprint $table) {
            // FIFO / available-stock lookups
            if (! $this->indexExists('product_batches', 'product_batches_fifo_lookup_idx')) {
                $table->index(
                    ['branch_id', 'variant_id', 'status', 'trash', 'received_date', 'id'],
                    'product_batches_fifo_lookup_idx'
                );
            }

            // Product show/edit: batches by variant
            if (! $this->indexExists('product_batches', 'product_batches_variant_list_idx')) {
                $table->index(
                    ['variant_id', 'trash', 'purchase_date', 'id'],
                    'product_batches_variant_list_idx'
                );
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_batches')) {
            return;
        }

        Schema::table('product_batches', function (Blueprint $table) {
            if ($this->indexExists('product_batches', 'product_batches_fifo_lookup_idx')) {
                $table->dropIndex('product_batches_fifo_lookup_idx');
            }
            if ($this->indexExists('product_batches', 'product_batches_variant_list_idx')) {
                $table->dropIndex('product_batches_variant_list_idx');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            foreach (Schema::getIndexes($table) as $row) {
                if (($row['name'] ?? null) === $index) {
                    return true;
                }
            }
        } catch (\Throwable) {
            // Older drivers: allow create/drop to proceed.
        }

        return false;
    }
};
