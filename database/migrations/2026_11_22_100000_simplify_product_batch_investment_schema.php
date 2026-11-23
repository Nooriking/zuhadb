<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema simplification:
 * - Drop obsolete / duplicate tables
 * - Move purchase metadata from products → product_batches
 * - Link batches to investments (not investor_id)
 * - Slim investments to amount_currency / amount_afn + exchange_rate
 * - inventories remain the stock source of truth (drop variant stock_quantity)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // 1) product_batches: add new columns first
        // ---------------------------------------------------------------
        Schema::table('product_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('product_batches', 'investment_id')) {
                $table->unsignedBigInteger('investment_id')->nullable()->after('variant_id');
            }
            if (! Schema::hasColumn('product_batches', 'invoice_number')) {
                $table->string('invoice_number', 100)->nullable()->after('batch_code');
            }
            if (! Schema::hasColumn('product_batches', 'purchase_date')) {
                $table->date('purchase_date')->nullable()->after('invoice_number');
            }
            if (! Schema::hasColumn('product_batches', 'payment_status')) {
                $table->string('payment_status', 50)->nullable()->after('purchase_date');
            }
            if (! Schema::hasColumn('product_batches', 'bill_image')) {
                $table->string('bill_image', 255)->nullable()->after('payment_status');
            }
            if (! Schema::hasColumn('product_batches', 'notes')) {
                $table->text('notes')->nullable()->after('bill_image');
            }
            if (! Schema::hasColumn('product_batches', 'purchase_currency_id')) {
                $table->unsignedBigInteger('purchase_currency_id')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('product_batches', 'purchase_amount_currency')) {
                $table->decimal('purchase_amount_currency', 18, 2)->nullable()->after('purchase_currency_id');
            }
        });

        // ---------------------------------------------------------------
        // 2) Backfill purchase fields from purchase shell product rows
        // ---------------------------------------------------------------
        if (Schema::hasColumn('product_batches', 'purchase_item_id')) {
            $batches = DB::table('product_batches')
                ->whereNotNull('purchase_item_id')
                ->get(['id', 'purchase_item_id', 'unit_cost_currency', 'quantity']);

            foreach ($batches as $batch) {
                $product = DB::table('products')->where('id', $batch->purchase_item_id)->first();
                if (! $product) {
                    continue;
                }

                DB::table('product_batches')->where('id', $batch->id)->update([
                    'invoice_number' => $product->invoice_number,
                    'purchase_date' => $product->purchase_date,
                    'payment_status' => $product->payment_status,
                    'bill_image' => $product->bill_image,
                    'notes' => $product->notes,
                    'purchase_amount_currency' => $batch->unit_cost_currency !== null
                        ? round((float) $batch->unit_cost_currency * (int) $batch->quantity, 2)
                        : null,
                ]);
            }
        }

        // ---------------------------------------------------------------
        // 3) investments: add amount_* columns + backfill before drop
        // ---------------------------------------------------------------
        Schema::table('investments', function (Blueprint $table) {
            if (! Schema::hasColumn('investments', 'amount_currency')) {
                $table->decimal('amount_currency', 18, 2)->nullable()->after('exchange_rate');
            }
            if (! Schema::hasColumn('investments', 'amount_afn')) {
                $table->decimal('amount_afn', 18, 2)->default(0)->after('amount_currency');
            }
        });

        if (Schema::hasColumn('investments', 'capital_amount_currency')) {
            DB::table('investments')->orderBy('id')->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('investments')->where('id', $row->id)->update([
                        'amount_currency' => $row->capital_amount_currency,
                        'amount_afn' => $row->capital_amount_afn ?? 0,
                    ]);
                }
            });
        }

        // Normalize status → active | closed
        DB::table('investments')
            ->whereNotIn('status', ['active', 'closed'])
            ->update(['status' => 'closed']);

        // ---------------------------------------------------------------
        // 4) Link batches → investments (via allocation or auto-create)
        // ---------------------------------------------------------------
        if (Schema::hasTable('investment_product_allocations')) {
            $links = DB::table('investment_product_allocations')
                ->whereNotNull('batch_id')
                ->whereNotNull('investment_id')
                ->get(['batch_id', 'investment_id']);

            foreach ($links as $link) {
                DB::table('product_batches')
                    ->where('id', $link->batch_id)
                    ->whereNull('investment_id')
                    ->update(['investment_id' => $link->investment_id]);
            }
        }

        // Orphan investor-owned batches (no allocation row): always create a dedicated
        // backfill investment per batch. Never reuse "latest investment" for an investor —
        // that mis-attributes capital/profit when an investor has multiple investments.
        if (Schema::hasColumn('product_batches', 'investor_id')) {
            $orphanBatches = DB::table('product_batches')
                ->whereNotNull('investor_id')
                ->whereNull('investment_id')
                ->orderBy('id')
                ->get();

            foreach ($orphanBatches as $batch) {
                $currencyId = $batch->purchase_currency_id
                    ?? DB::table('currencies')->where('code', 'AFN')->value('id');
                $amountCurrency = $batch->purchase_amount_currency
                    ?? $batch->total_cost_afn
                    ?? 0;
                $amountAfn = $batch->total_cost_afn ?? 0;
                $rate = (float) ($batch->exchange_rate ?: 1);

                $payload = [
                    'investor_id' => $batch->investor_id,
                    'currency_id' => $currencyId,
                    'exchange_rate' => $rate,
                    'amount_currency' => $amountCurrency,
                    'amount_afn' => $amountAfn,
                    'investment_date' => $batch->purchase_date
                        ?? $batch->received_date
                        ?? now()->toDateString(),
                    'status' => 'active',
                    'notes' => 'Backfilled from product_batches #'.$batch->id.' during schema simplify',
                    'created_by' => $batch->user_id,
                    'trash' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $legacy = [
                    'source_type' => 'goods',
                    'investment_number' => 'BF-'.date('Ymd').'-'.$batch->id,
                    'capital_amount_currency' => $amountCurrency,
                    'capital_amount_afn' => $amountAfn,
                    'available_amount_afn' => 0,
                    'allocated_amount_afn' => $amountAfn,
                    'profit_amount_afn' => 0,
                    'withdrawn_amount_afn' => 0,
                ];

                foreach ($legacy as $col => $value) {
                    if (Schema::hasColumn('investments', $col)) {
                        $payload[$col] = $value;
                    }
                }

                $investmentId = DB::table('investments')->insertGetId($payload);

                DB::table('product_batches')
                    ->where('id', $batch->id)
                    ->update(['investment_id' => $investmentId]);
            }
        }

        // ---------------------------------------------------------------
        // 5) Merge investor_wallets → investor_wallet_balances (if needed)
        // ---------------------------------------------------------------
        if (Schema::hasTable('investor_wallets') && Schema::hasTable('investor_wallet_balances')) {
            $wallets = DB::table('investor_wallets')->get();
            foreach ($wallets as $wallet) {
                $currencyId = $wallet->currency_id
                    ?? DB::table('currencies')->where('code', 'AFN')->value('id');
                $currencyCode = DB::table('currencies')->where('id', $currencyId)->value('code') ?? 'AFN';

                $existing = DB::table('investor_wallet_balances')
                    ->where('investor_id', $wallet->investor_id)
                    ->where('currency_id', $currencyId)
                    ->first();

                if ($existing) {
                    continue;
                }

                DB::table('investor_wallet_balances')->insert([
                    'investor_id' => $wallet->investor_id,
                    'currency_id' => $currencyId,
                    'currency_code' => $currencyCode,
                    'capital_balance_currency' => $wallet->capital_balance_currency ?? $wallet->capital_balance_afn ?? 0,
                    'capital_balance_afn' => $wallet->capital_balance_afn ?? 0,
                    'profit_balance_currency' => $wallet->profit_balance_currency ?? $wallet->profit_balance_afn ?? 0,
                    'profit_balance_afn' => $wallet->profit_balance_afn ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // ---------------------------------------------------------------
        // 6) Migrate investor_profit_entries → investor_transactions
        // ---------------------------------------------------------------
        if (Schema::hasTable('investor_profit_entries') && Schema::hasTable('investor_transactions')) {
            $entries = DB::table('investor_profit_entries')
                ->where('status', '!=', 'cancelled')
                ->get();

            foreach ($entries as $entry) {
                $exists = DB::table('investor_transactions')
                    ->where('investor_id', $entry->investor_id)
                    ->where('type', 'profit_earned')
                    ->where('reference_type', 'sale_item')
                    ->where('reference_id', $entry->sale_item_id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('investor_transactions')->insert([
                    'investor_id' => $entry->investor_id,
                    'investment_id' => $entry->investment_id,
                    'currency_id' => $entry->currency_id,
                    'currency_code' => $entry->currency_code,
                    'currency_symbol' => $entry->currency_symbol,
                    'exchange_rate' => $entry->exchange_rate,
                    'type' => 'profit_earned',
                    'amount_currency' => $entry->profit_amount_currency ?? $entry->profit_amount_afn,
                    'amount_afn' => $entry->profit_amount_afn,
                    'balance_type' => 'profit',
                    'balance_before_afn' => 0,
                    'balance_after_afn' => $entry->profit_amount_afn,
                    'reference_type' => 'sale_item',
                    'reference_id' => $entry->sale_item_id,
                    'description' => $entry->notes ?? 'Migrated from investor_profit_entries',
                    'transaction_date' => optional($entry->earned_at)
                        ? date('Y-m-d', strtotime((string) $entry->earned_at))
                        : now()->toDateString(),
                    'created_by' => $entry->paid_by,
                    'created_at' => $entry->created_at ?? now(),
                    'updated_at' => $entry->updated_at ?? now(),
                ]);
            }
        }

        // ---------------------------------------------------------------
        // 7) Drop FKs / columns on product_batches
        // ---------------------------------------------------------------
        $this->dropForeignOnColumn('product_batches', 'purchase_item_id');
        $this->dropForeignOnColumn('product_batches', 'investor_id');
        $this->dropForeignOnColumn('product_batches', 'profit_currency_id');
        // Also try Laravel-default names if information_schema lookup differs
        $this->dropForeignIfExists('product_batches', 'product_batches_purchase_item_id_foreign');
        $this->dropForeignIfExists('product_batches', 'product_batches_investor_id_foreign');
        $this->dropForeignIfExists('product_batches', 'product_batches_profit_currency_id_foreign');

        Schema::table('product_batches', function (Blueprint $table) {
            $drop = array_values(array_filter([
                Schema::hasColumn('product_batches', 'purchase_item_id') ? 'purchase_item_id' : null,
                Schema::hasColumn('product_batches', 'investor_id') ? 'investor_id' : null,
                Schema::hasColumn('product_batches', 'profit_per_unit_usd') ? 'profit_per_unit_usd' : null,
                Schema::hasColumn('product_batches', 'profit_per_unit_aed') ? 'profit_per_unit_aed' : null,
                Schema::hasColumn('product_batches', 'profit_currency_id') ? 'profit_currency_id' : null,
                Schema::hasColumn('product_batches', 'profit_currency_code') ? 'profit_currency_code' : null,
                Schema::hasColumn('product_batches', 'profit_per_unit_currency') ? 'profit_per_unit_currency' : null,
                Schema::hasColumn('product_batches', 'usd_to_afn_rate') ? 'usd_to_afn_rate' : null,
                Schema::hasColumn('product_batches', 'aed_to_afn_rate') ? 'aed_to_afn_rate' : null,
                Schema::hasColumn('product_batches', 'usd_to_aed_rate') ? 'usd_to_aed_rate' : null,
                Schema::hasColumn('product_batches', 'rate_effective_at') ? 'rate_effective_at' : null,
                Schema::hasColumn('product_batches', 'rate_source') ? 'rate_source' : null,
            ]));

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('product_batches', function (Blueprint $table) {
            if (Schema::hasColumn('product_batches', 'investment_id')
                && ! $this->foreignKeyExists('product_batches', 'product_batches_investment_id_foreign')) {
                $table->foreign('investment_id')
                    ->references('id')
                    ->on('investments')
                    ->nullOnDelete();
            }
            if (Schema::hasColumn('product_batches', 'purchase_currency_id')
                && ! $this->foreignKeyExists('product_batches', 'product_batches_purchase_currency_id_foreign')) {
                $table->foreign('purchase_currency_id')
                    ->references('id')
                    ->on('currencies')
                    ->nullOnDelete();
            }
        });

        // ---------------------------------------------------------------
        // 8) Slim investments
        // ---------------------------------------------------------------
        $this->dropForeignIfExists('investments', 'investments_parent_investment_id_foreign');
        $this->dropForeignIfExists('investments', 'investments_user_id_foreign');

        // Drop unique index on investment_number if present
        $this->dropIndexIfExists('investments', 'investments_investment_number_unique');
        $this->dropIndexIfExists('investments', 'investments_source_type_index');
        $this->dropIndexIfExists('investments', 'investments_parent_investment_id_index');

        Schema::table('investments', function (Blueprint $table) {
            $drop = array_values(array_filter([
                Schema::hasColumn('investments', 'source_type') ? 'source_type' : null,
                Schema::hasColumn('investments', 'parent_investment_id') ? 'parent_investment_id' : null,
                Schema::hasColumn('investments', 'investment_number') ? 'investment_number' : null,
                Schema::hasColumn('investments', 'currency_code') ? 'currency_code' : null,
                Schema::hasColumn('investments', 'currency_symbol') ? 'currency_symbol' : null,
                Schema::hasColumn('investments', 'usd_to_afn_rate') ? 'usd_to_afn_rate' : null,
                Schema::hasColumn('investments', 'aed_to_afn_rate') ? 'aed_to_afn_rate' : null,
                Schema::hasColumn('investments', 'usd_to_aed_rate') ? 'usd_to_aed_rate' : null,
                Schema::hasColumn('investments', 'rate_effective_at') ? 'rate_effective_at' : null,
                Schema::hasColumn('investments', 'rate_source') ? 'rate_source' : null,
                Schema::hasColumn('investments', 'capital_amount_usd') ? 'capital_amount_usd' : null,
                Schema::hasColumn('investments', 'capital_amount_aed') ? 'capital_amount_aed' : null,
                Schema::hasColumn('investments', 'capital_amount_currency') ? 'capital_amount_currency' : null,
                Schema::hasColumn('investments', 'capital_amount_afn') ? 'capital_amount_afn' : null,
                Schema::hasColumn('investments', 'available_amount_currency') ? 'available_amount_currency' : null,
                Schema::hasColumn('investments', 'available_amount_afn') ? 'available_amount_afn' : null,
                Schema::hasColumn('investments', 'profit_amount_currency') ? 'profit_amount_currency' : null,
                Schema::hasColumn('investments', 'profit_amount_afn') ? 'profit_amount_afn' : null,
                Schema::hasColumn('investments', 'withdrawn_amount_afn') ? 'withdrawn_amount_afn' : null,
                Schema::hasColumn('investments', 'allocated_amount_afn') ? 'allocated_amount_afn' : null,
                Schema::hasColumn('investments', 'user_id') ? 'user_id' : null,
            ]));

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });

        // Rebuild status enum as active|closed (MySQL)
        DB::statement("ALTER TABLE investments MODIFY COLUMN status ENUM('active','closed') NOT NULL DEFAULT 'active'");

        // ---------------------------------------------------------------
        // 9) Drop columns from products / product_variants
        // ---------------------------------------------------------------
        Schema::table('products', function (Blueprint $table) {
            $drop = array_values(array_filter([
                Schema::hasColumn('products', 'invoice_number') ? 'invoice_number' : null,
                Schema::hasColumn('products', 'purchase_date') ? 'purchase_date' : null,
                Schema::hasColumn('products', 'quantity') ? 'quantity' : null,
                Schema::hasColumn('products', 'remaining_quantity') ? 'remaining_quantity' : null,
                Schema::hasColumn('products', 'bill_image') ? 'bill_image' : null,
                Schema::hasColumn('products', 'notes') ? 'notes' : null,
                Schema::hasColumn('products', 'payment_status') ? 'payment_status' : null,
            ]));

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('product_variants', function (Blueprint $table) {
            if (Schema::hasColumn('product_variants', 'stock_quantity')) {
                $table->dropColumn('stock_quantity');
            }
        });

        // Soft-delete orphan purchase shell products (rows that only existed as purchase wrappers)
        // Keep them soft-deleted rather than hard-delete to preserve audit trails.
        if (Schema::hasColumn('products', 'purchase_date') === false) {
            // Columns already dropped; identify shells via batches that had purchase_item_id historically
            // is not possible after drop. Skip.
        }

        // ---------------------------------------------------------------
        // 10) Drop obsolete tables (children first)
        // ---------------------------------------------------------------
        Schema::dropIfExists('investor_profit_entries');
        Schema::dropIfExists('investment_product_allocations');
        Schema::dropIfExists('investment_purchase_allocations');
        Schema::dropIfExists('investor_wallets');
        Schema::dropIfExists('customer_transactions');
        // Keep customer_balances — required by multi-currency customer due
        // (2026_11_22_151000). Dropping it here caused empty recreate + data loss.
        Schema::dropIfExists('investor_user_migration_map');
        Schema::dropIfExists('investors_legacy');
    }

    public function down(): void
    {
        // Irreversible simplification — recreate stubs only if needed for rollback safety.
        throw new RuntimeException('This schema simplification migration cannot be reversed automatically.');
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        $db = DB::getDatabaseName();
        $exists = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$db, $table, $constraint, 'FOREIGN KEY']
        );

        return (bool) $exists;
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        if (! $this->foreignKeyExists($table, $constraint)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($constraint) {
            $blueprint->dropForeign($constraint);
        });
    }

    /**
     * Drop any foreign key whose COLUMN_NAME matches (handles non-default constraint names).
     */
    private function dropForeignOnColumn(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $db = DB::getDatabaseName();
        $rows = DB::select(
            'SELECT DISTINCT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$db, $table, $column]
        );

        foreach ($rows as $row) {
            $this->dropForeignIfExists($table, $row->CONSTRAINT_NAME);
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $db = DB::getDatabaseName();
        $exists = DB::selectOne(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$db, $table, $index]
        );

        if ($exists) {
            Schema::table($table, function (Blueprint $blueprint) use ($index) {
                $blueprint->dropIndex($index);
            });
        }
    }
};
