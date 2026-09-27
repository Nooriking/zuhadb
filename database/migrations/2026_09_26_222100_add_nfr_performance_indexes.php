<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('sales', 'sales_branch_created_idx', function (Blueprint $table) {
            $table->index(['branch_id', 'created_at'], 'sales_branch_created_idx');
        });
        $this->addIndexIfMissing('sales', 'sales_customer_created_idx', function (Blueprint $table) {
            $table->index(['customer_id', 'created_at'], 'sales_customer_created_idx');
        });
        $this->addIndexIfMissing('sales', 'sales_status_created_idx', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'sales_status_created_idx');
        });

        $this->addIndexIfMissing('inventories', 'inventories_quantity_idx', function (Blueprint $table) {
            $table->index(['quantity'], 'inventories_quantity_idx');
        });

        $this->addIndexIfMissing('inventory_transactions', 'inv_tx_branch_created_idx', function (Blueprint $table) {
            $table->index(['branch_id', 'created_at'], 'inv_tx_branch_created_idx');
        });
        $this->addIndexIfMissing('inventory_transactions', 'inv_tx_variant_created_idx', function (Blueprint $table) {
            $table->index(['variant_id', 'created_at'], 'inv_tx_variant_created_idx');
        });
        $this->addIndexIfMissing('inventory_transactions', 'inv_tx_type_created_idx', function (Blueprint $table) {
            $table->index(['type', 'created_at'], 'inv_tx_type_created_idx');
        });

        $this->addIndexIfMissing('users', 'users_status_trash_idx', function (Blueprint $table) {
            $table->index(['status', 'trash'], 'users_status_trash_idx');
        });

        $this->addIndexIfMissing('customers', 'customers_phone_idx', function (Blueprint $table) {
            $table->index('phone', 'customers_phone_idx');
        });
        $this->addIndexIfMissing('customers', 'customers_status_trash_idx', function (Blueprint $table) {
            $table->index(['status', 'trash'], 'customers_status_trash_idx');
        });
    }

    public function down(): void
    {
        foreach ([
            ['sales', 'sales_branch_created_idx'],
            ['sales', 'sales_customer_created_idx'],
            ['sales', 'sales_status_created_idx'],
            ['inventories', 'inventories_quantity_idx'],
            ['inventory_transactions', 'inv_tx_branch_created_idx'],
            ['inventory_transactions', 'inv_tx_variant_created_idx'],
            ['inventory_transactions', 'inv_tx_type_created_idx'],
            ['users', 'users_status_trash_idx'],
            ['customers', 'customers_phone_idx'],
            ['customers', 'customers_status_trash_idx'],
        ] as [$table, $index]) {
            if ($this->indexExists($table, $index)) {
                Schema::table($table, function (Blueprint $blueprint) use ($index) {
                    $blueprint->dropIndex($index);
                });
            }
        }
    }

    private function addIndexIfMissing(string $table, string $index, callable $callback): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, $callback);
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = Schema::getConnection()->getDatabaseName();
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );

        return ((int) ($row->c ?? 0)) > 0;
    }
};
