<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Investor financial FKs reference users.id.
 * Prevent cascade-wiping ledgers when a user row is deleted.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $restrictTables = [
        'investments',
        'investor_wallets',
        'investor_transactions',
        'investor_profit_entries',
        'investor_withdrawals',
    ];

    /** @var list<string> */
    private array $nullOnDeleteTables = [
        'products',
        'product_batches',
    ];

    public function up(): void
    {
        foreach ($this->restrictTables as $table) {
            $this->repointForeignKey($table, 'restrict');
        }

        foreach ($this->nullOnDeleteTables as $table) {
            $this->repointForeignKey($table, 'null');
        }
    }

    public function down(): void
    {
        foreach (array_merge($this->restrictTables, $this->nullOnDeleteTables) as $table) {
            $this->repointForeignKey($table, 'cascade');
        }
    }

    private function repointForeignKey(string $table, string $onDelete): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'investor_id')) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['investor_id']);
            });
        } catch (\Throwable) {
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $blueprint->dropForeign($table.'_investor_id_foreign');
                });
            } catch (\Throwable) {
                // already absent
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($onDelete) {
            $fk = $blueprint->foreign('investor_id')->references('id')->on('users');

            match ($onDelete) {
                'restrict' => $fk->restrictOnDelete(),
                'null' => $fk->nullOnDelete(),
                default => $fk->cascadeOnDelete(),
            };
        });
    }
};
