<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = DB::getDatabaseName();

        $foreignKeys = DB::select(
            'SELECT TABLE_NAME, CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND REFERENCED_TABLE_NAME IN (?, ?, ?)',
            [$schema, 'currencies', 'exchange_rates', 'exchange_rate_history']
        );

        foreach ($foreignKeys as $foreignKey) {
            DB::statement(
                "ALTER TABLE `{$foreignKey->TABLE_NAME}` DROP FOREIGN KEY `{$foreignKey->CONSTRAINT_NAME}`"
            );
        }

        $columns = collect(DB::select(
            'SELECT TABLE_NAME, COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME NOT IN (?, ?, ?, ?)',
            [$schema, 'migrations', 'currencies', 'exchange_rates', 'exchange_rate_history']
        ))->filter(function ($column) {
            $name = $column->COLUMN_NAME;

            return $name === 'currency_id'
                || $name === 'exchange_rate'
                || $name === 'purchase_price_usd'
                || $name === 'from_currency_id'
                || $name === 'to_currency_id'
                || str_ends_with($name, '_currency')
                || str_ends_with($name, '_currency_id');
        })->values();

        $byTable = [];
        foreach ($columns as $column) {
            $byTable[$column->TABLE_NAME][] = $column->COLUMN_NAME;
        }

        if ($byTable !== []) {
            $names = $columns->pluck('COLUMN_NAME')->unique()->values()->all();
            $placeholders = implode(',', array_fill(0, count($names), '?'));

            $indexes = DB::select(
                "SELECT DISTINCT TABLE_NAME, INDEX_NAME
                 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = ?
                   AND INDEX_NAME <> 'PRIMARY'
                   AND COLUMN_NAME IN ({$placeholders})",
                array_merge([$schema], $names)
            );

            foreach ($indexes as $index) {
                DB::statement("ALTER TABLE `{$index->TABLE_NAME}` DROP INDEX `{$index->INDEX_NAME}`");
            }
        }

        foreach ($byTable as $table => $cols) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($cols) {
                $blueprint->dropColumn($cols);
            });
        }

        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('exchange_rate_history');
        Schema::dropIfExists('currencies');
    }

    public function down(): void
    {
        // Currency support was removed. Restoring the old multi-currency schema is not supported.
    }
};
