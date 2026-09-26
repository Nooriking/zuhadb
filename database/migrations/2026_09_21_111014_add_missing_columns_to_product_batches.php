<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_batches', function (Blueprint $table) {
            /* ⭐ purchase_item_id → FK به products */
            if (! Schema::hasColumn('product_batches', 'purchase_item_id')) {
                $table->foreignId('purchase_item_id')
                    ->nullable()
                    ->after('variant_id')
                    ->constrained('products')
                    ->nullOnDelete();
            }

            /* ⭐ investor_id */
            if (! Schema::hasColumn('product_batches', 'investor_id')) {
                $table->foreignId('investor_id')
                    ->nullable()
                    ->after('purchase_item_id')
                    ->constrained('investors')
                    ->nullOnDelete();
            }

            /* ⭐ total_cost_currency */
            if (! Schema::hasColumn('product_batches', 'total_cost_currency')) {
                $table->decimal('total_cost_currency', 18, 2)
                    ->default(0)
                    ->after('unit_cost_afn');
            }

            /* ⭐ total_cost_afn */
            if (! Schema::hasColumn('product_batches', 'total_cost_afn')) {
                $table->decimal('total_cost_afn', 18, 2)
                    ->default(0)
                    ->after('total_cost_currency');
            }

            /* ⭐ profit_per_unit_currency */
            if (! Schema::hasColumn('product_batches', 'profit_per_unit_currency')) {
                $table->decimal('profit_per_unit_currency', 18, 2)
                    ->default(0)
                    ->after('total_cost_afn');
            }

            /* ⭐ profit_per_unit_afn */
            if (! Schema::hasColumn('product_batches', 'profit_per_unit_afn')) {
                $table->decimal('profit_per_unit_afn', 18, 2)
                    ->default(0)
                    ->after('profit_per_unit_currency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_batches', function (Blueprint $table) {
            $columns = [
                'purchase_item_id',
                'investor_id',
                'total_cost_currency',
                'total_cost_afn',
                'profit_per_unit_currency',
                'profit_per_unit_afn',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('product_batches', $column)) {
                    try {
                        $table->dropForeign([$column]);
                    } catch (\Exception $e) {
                        // ignore if no FK
                    }
                    $table->dropColumn($column);
                }
            }
        });
    }
};