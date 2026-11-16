<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Refactor: investors identity → users.
 *
 * Convention after this migration:
 * - Column name `investor_id` is retained for compatibility.
 * - `investor_id` references `users.id` (users with role=investor).
 * - Legacy `investors` rows are archived as `investors_legacy` (not dropped).
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tablesWithInvestorId = [
        'investments',
        'investor_wallets',
        'investor_transactions',
        'investor_profit_entries',
        'investor_withdrawals',
        'products',
        'product_batches',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('investors')) {
            $this->ensureInvestorForeignKeysPointToUsers();

            return;
        }

        $mapping = $this->ensureInvestorUserMapping();
        $hasFinancialRows = $this->hasAnyInvestorForeignKeyRows();

        if ($hasFinancialRows) {
            $this->remapInvestorForeignKeys($mapping);
            $this->swapInvestorIdColumnsToUsers();
        } else {
            $this->rewireEmptyForeignKeysToUsers();
        }

        $this->archiveInvestorsTable();
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Cannot automatically roll back investor→user identity migration. Restore from backup.'
        );
    }

    /**
     * @return array<int, int> old investors.id => users.id
     */
    private function ensureInvestorUserMapping(): array
    {
        $mapping = [];
        $unmatched = [];

        $investors = DB::table('investors')->orderBy('id')->get();

        foreach ($investors as $investor) {
            $userId = $investor->user_id ? (int) $investor->user_id : null;

            if ($userId) {
                $user = DB::table('users')->where('id', $userId)->first();
                if (! $user) {
                    $unmatched[] = [
                        'investor_id' => $investor->id,
                        'reason' => 'linked_user_missing',
                        'user_id' => $userId,
                    ];
                    $userId = null;
                } elseif ($user->role !== User::ROLE_INVESTOR) {
                    DB::table('users')->where('id', $userId)->update([
                        'role' => User::ROLE_INVESTOR,
                        'updated_at' => now(),
                    ]);
                }
            }

            if (! $userId) {
                $userId = $this->findOrCreateUserForInvestor($investor);
                if (! $userId) {
                    $unmatched[] = [
                        'investor_id' => $investor->id,
                        'reason' => 'could_not_create_or_match_user',
                        'full_name' => $investor->full_name,
                        'email' => $investor->email,
                        'phone' => $investor->phone,
                    ];
                    continue;
                }

                DB::table('investors')->where('id', $investor->id)->update([
                    'user_id' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if (in_array($userId, $mapping, true)) {
                $unmatched[] = [
                    'investor_id' => $investor->id,
                    'reason' => 'duplicate_user_mapping',
                    'user_id' => $userId,
                ];
                continue;
            }

            $mapping[(int) $investor->id] = $userId;
        }

        if ($unmatched !== []) {
            Log::warning('Investor→user migration unmatched records', $unmatched);
            throw new RuntimeException(
                'Investor→user migration blocked: '.count($unmatched)
                .' unmatched/ambiguous investor record(s). See laravel.log for details.'
            );
        }

        Schema::dropIfExists('investor_user_migration_map');
        Schema::create('investor_user_migration_map', function (Blueprint $table) {
            $table->unsignedBigInteger('old_investor_id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });

        $now = now();
        foreach ($mapping as $oldId => $userId) {
            DB::table('investor_user_migration_map')->insert([
                'old_investor_id' => $oldId,
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $mapping;
    }

    private function findOrCreateUserForInvestor(object $investor): ?int
    {
        if (! empty($investor->email)) {
            $existing = DB::table('users')->where('email', $investor->email)->first();
            if ($existing) {
                if ($existing->role !== User::ROLE_INVESTOR) {
                    Log::warning('Investor email matches non-investor user', [
                        'investor_id' => $investor->id,
                        'user_id' => $existing->id,
                        'email' => $investor->email,
                    ]);

                    return null;
                }

                return (int) $existing->id;
            }
        }

        if (! empty($investor->phone)) {
            $existing = DB::table('users')
                ->where('phone', $investor->phone)
                ->where('role', User::ROLE_INVESTOR)
                ->first();
            if ($existing) {
                return (int) $existing->id;
            }
        }

        $email = $investor->email;
        if (empty($email)) {
            $email = 'investor-migrated-'.$investor->id.'@local.invalid';
            while (DB::table('users')->where('email', $email)->exists()) {
                $email = 'investor-migrated-'.$investor->id.'-'.Str::lower(Str::random(4)).'@local.invalid';
            }
        }

        return (int) DB::table('users')->insertGetId([
            'name' => $investor->full_name ?: ('Investor #'.$investor->id),
            'email' => $email,
            'phone' => $investor->phone,
            'password' => Hash::make(Str::random(40)),
            'role' => User::ROLE_INVESTOR,
            'status' => ($investor->status ?? 'active') === 'inactive' ? 'inactive' : 'active',
            'trash' => (bool) ($investor->trash ?? false),
            'user_id' => $investor->created_by,
            'created_at' => $investor->created_at ?? now(),
            'updated_at' => now(),
            'deleted_at' => $investor->deleted_at,
        ]);
    }

    private function hasAnyInvestorForeignKeyRows(): bool
    {
        foreach ($this->tablesWithInvestorId as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'investor_id')) {
                continue;
            }

            if (DB::table($table)->whereNotNull('investor_id')->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, int>  $mapping
     */
    private function remapInvestorForeignKeys(array $mapping): void
    {
        foreach ($this->tablesWithInvestorId as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'investor_id')) {
                continue;
            }

            if (! Schema::hasColumn($table, 'investor_user_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->unsignedBigInteger('investor_user_id')->nullable()->after('investor_id');
                });
            }

            foreach ($mapping as $oldId => $userId) {
                DB::table($table)->where('investor_id', $oldId)->update([
                    'investor_user_id' => $userId,
                ]);
            }

            $orphans = DB::table($table)
                ->whereNotNull('investor_id')
                ->whereNull('investor_user_id')
                ->count();

            if ($orphans > 0) {
                throw new RuntimeException(
                    "Orphan investor_id values on {$table}: {$orphans} row(s) could not be mapped."
                );
            }
        }

        if (Schema::hasTable('investments') && Schema::hasColumn('investments', 'user_id') && Schema::hasColumn('investments', 'investor_user_id')) {
            DB::statement('UPDATE investments SET user_id = investor_user_id WHERE investor_user_id IS NOT NULL');
        }
    }

    private function swapInvestorIdColumnsToUsers(): void
    {
        foreach ($this->tablesWithInvestorId as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'investor_user_id')) {
                continue;
            }

            $this->dropForeignKeyIfExists($table, 'investor_id');

            if ($table === 'investor_wallets') {
                $this->dropIndexIfExists($table, 'investor_wallets_investor_id_unique');
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('investor_id');
            });

            DB::statement("ALTER TABLE `{$table}` CHANGE `investor_user_id` `investor_id` BIGINT UNSIGNED NOT NULL");

            // products / product_batches allow null ownership
            if (in_array($table, ['products', 'product_batches'], true)) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `investor_id` BIGINT UNSIGNED NULL");
            }

            $this->addInvestorForeignKeyToUsers($table);
        }
    }

    private function rewireEmptyForeignKeysToUsers(): void
    {
        foreach ($this->tablesWithInvestorId as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'investor_id')) {
                continue;
            }

            $this->dropForeignKeyIfExists($table, 'investor_id');
            $this->addInvestorForeignKeyToUsers($table);
        }
    }

    private function ensureInvestorForeignKeysPointToUsers(): void
    {
        $this->rewireEmptyForeignKeysToUsers();
    }

    private function addInvestorForeignKeyToUsers(string $table): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            $nullable = in_array($table, ['products', 'product_batches'], true);

            $fk = $blueprint->foreign('investor_id')->references('id')->on('users');
            if ($nullable) {
                $fk->nullOnDelete();
            } else {
                $fk->cascadeOnDelete();
            }

            if ($table === 'investor_wallets') {
                // Unique may already exist; ignore failure later.
            }
        });

        if ($table === 'investor_wallets') {
            try {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->unique('investor_id');
                });
            } catch (\Throwable) {
                // already unique
            }
        }
    }

    private function archiveInvestorsTable(): void
    {
        if (Schema::hasTable('investors_legacy')) {
            Schema::drop('investors_legacy');
        }

        Schema::rename('investors', 'investors_legacy');
    }

    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });
        } catch (\Throwable) {
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($table, $column) {
                    $blueprint->dropForeign($table.'_'.$column.'_foreign');
                });
            } catch (\Throwable) {
                // already absent
            }
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($index) {
                $blueprint->dropUnique($index);
            });
        } catch (\Throwable) {
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($index) {
                    $blueprint->dropIndex($index);
                });
            } catch (\Throwable) {
                // ignore
            }
        }
    }
};
