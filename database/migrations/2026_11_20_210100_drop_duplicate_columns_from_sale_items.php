<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $columns = [
        'sale_currency_code',
        'sale_unit_amount',
        'sale_line_amount',
        'unit_amount_usd',
        'unit_amount_aed',
        'line_amount_usd',
        'line_amount_aed',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('sale_items')) {
            return;
        }

        $existing = array_values(array_filter(
            $this->columns,
            fn (string $col) => Schema::hasColumn('sale_items', $col)
        ));

        if ($existing === []) {
            return;
        }

        Schema::table('sale_items', function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sale_items')) {
            return;
        }

        Schema::table('sale_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_items', 'sale_currency_code')) {
                $table->string('sale_currency_code', 10)->nullable()->after('barcode_snapshot');
            }
            if (! Schema::hasColumn('sale_items', 'sale_unit_amount')) {
                $table->decimal('sale_unit_amount', 18, 4)->nullable();
            }
            if (! Schema::hasColumn('sale_items', 'sale_line_amount')) {
                $table->decimal('sale_line_amount', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('sale_items', 'unit_amount_usd')) {
                $table->decimal('unit_amount_usd', 18, 4)->nullable();
            }
            if (! Schema::hasColumn('sale_items', 'unit_amount_aed')) {
                $table->decimal('unit_amount_aed', 18, 4)->nullable();
            }
            if (! Schema::hasColumn('sale_items', 'line_amount_usd')) {
                $table->decimal('line_amount_usd', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('sale_items', 'line_amount_aed')) {
                $table->decimal('line_amount_aed', 18, 2)->nullable();
            }
        });
    }
};
