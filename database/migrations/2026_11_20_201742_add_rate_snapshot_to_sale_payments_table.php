<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sale_payments')) {
            return;
        }

        Schema::table('sale_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_payments', 'rate_effective_at')) {
                $table->dateTime('rate_effective_at')->nullable()->after('rate_kind');
            }
            if (! Schema::hasColumn('sale_payments', 'rate_source')) {
                $table->string('rate_source', 40)->nullable()->after('rate_effective_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sale_payments')) {
            return;
        }

        Schema::table('sale_payments', function (Blueprint $table) {
            $cols = array_values(array_filter([
                Schema::hasColumn('sale_payments', 'rate_source') ? 'rate_source' : null,
                Schema::hasColumn('sale_payments', 'rate_effective_at') ? 'rate_effective_at' : null,
            ]));

            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
