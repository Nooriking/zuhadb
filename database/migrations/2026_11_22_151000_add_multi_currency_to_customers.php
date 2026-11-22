<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 1 — Multi-currency Customer Due
 *
 * Source of truth per currency: customer_balances (already created 2026_11_18_220400).
 * customers.current_due_afn stays as the denormalized AFN-weighted total.
 *
 * This migration:
 * 1. Ensures customer_balances exists (idempotent)
 * 2. Backfills one AFN balance row per customer that has current_due_afn ≠ 0
 *    and no existing balance rows yet
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_balances')) {
            Schema::create('customer_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('currency_id')->constrained('currencies')->cascadeOnDelete();
                $table->string('currency_code', 10);
                $table->decimal('balance_currency', 18, 2)->default(0);
                $table->decimal('balance_afn', 18, 2)->default(0);
                $table->decimal('last_rate', 18, 8)->nullable();
                $table->dateTime('last_rate_at')->nullable();
                $table->timestamps();

                $table->unique(['customer_id', 'currency_id']);
            });
        }

        if (! Schema::hasTable('customers') || ! Schema::hasTable('currencies')) {
            return;
        }

        $afn = DB::table('currencies')
            ->where('code', 'AFN')
            ->when(Schema::hasColumn('currencies', 'trash'), fn ($q) => $q->where('trash', false))
            ->first();

        if (! $afn) {
            return;
        }

        $now = now();

        $customers = DB::table('customers')
            ->where(function ($q) {
                $q->where('current_due_afn', '>', 0)
                    ->orWhere('current_due_afn', '<', 0);
            })
            ->when(Schema::hasColumn('customers', 'trash'), fn ($q) => $q->where('trash', false))
            ->select(['id', 'current_due_afn'])
            ->get();

        foreach ($customers as $customer) {
            $hasAnyBalance = DB::table('customer_balances')
                ->where('customer_id', $customer->id)
                ->exists();

            // Skip if multi-currency rows already exist (manual / prior run).
            if ($hasAnyBalance) {
                continue;
            }

            $dueAfn = round((float) $customer->current_due_afn, 2);

            DB::table('customer_balances')->insert([
                'customer_id' => $customer->id,
                'currency_id' => $afn->id,
                'currency_code' => 'AFN',
                'balance_currency' => $dueAfn,
                'balance_afn' => $dueAfn,
                'last_rate' => 1,
                'last_rate_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Do not drop customer_balances — owned by 2026_11_18_220400.
        // Backfilled AFN rows are left in place (safe; balances remain valid).
    }
};
