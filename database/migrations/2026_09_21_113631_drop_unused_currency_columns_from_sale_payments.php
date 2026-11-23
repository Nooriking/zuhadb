<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ═══════════════════════════════════════════════════════
         |  ⭐ 1. Find and drop FKs (if they exist)
         ═══════════════════════════════════════════════════════ */

        $foreignKeys = $this->getForeignKeys('sale_payments');

        foreach ($foreignKeys as $fk) {
            // Only FKs that reference currency_id
            if (str_contains($fk->COLUMN_NAME, 'currency_id')) {
                try {
                    DB::statement("ALTER TABLE `sale_payments` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
                } catch (\Exception $e) {
                    // ignore
                }
            }
        }

        /* ═══════════════════════════════════════════════════════
         |  ⭐ 2. Drop columns
         ═══════════════════════════════════════════════════════ */

        Schema::table('sale_payments', function (Blueprint $table) {
            $columnsToDrop = [];

            foreach (['currency_id', 'exchange_rate', 'amount_currency'] as $col) {
                if (Schema::hasColumn('sale_payments', $col)) {
                    $columnsToDrop[] = $col;
                }
            }

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_payments', 'currency_id')) {
                $table->foreignId('currency_id')
                    ->nullable()
                    ->after('sale_id');
            }

            if (! Schema::hasColumn('sale_payments', 'exchange_rate')) {
                $table->decimal('exchange_rate', 18, 6)
                    ->default(1)
                    ->after('currency_id');
            }

            if (! Schema::hasColumn('sale_payments', 'amount_currency')) {
                $table->decimal('amount_currency', 18, 2)
                    ->default(0)
                    ->after('exchange_rate');
            }
        });
    }

    /**
     * ⭐ List FKs for a table
     */
    private function getForeignKeys(string $table): array
    {
        return DB::select("
            SELECT CONSTRAINT_NAME, COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$table]);
    }
};