<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('products', 'units_per_pack') ? 'units_per_pack' : null,
            Schema::hasColumn('products', 'pack_unit_name') ? 'pack_unit_name' : null,
            Schema::hasColumn('products', 'base_unit_name') ? 'base_unit_name' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('products', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'base_unit_name')) {
                $table->string('base_unit_name', 50)->nullable()->after('description');
            }
            if (! Schema::hasColumn('products', 'pack_unit_name')) {
                $table->string('pack_unit_name', 50)->nullable()->after('base_unit_name');
            }
            if (! Schema::hasColumn('products', 'units_per_pack')) {
                $table->decimal('units_per_pack', 18, 6)->nullable()->after('pack_unit_name');
            }
        });
    }
};
