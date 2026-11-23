<?php

namespace App\Ai\Database;

use App\Models\User;

/**
 * Maps application tables to ERP view permissions and role constraints.
 */
final class TableAccessPolicy
{
    public function isDeniedTable(string $table): bool
    {
        $table = strtolower(trim($table));
        $denied = array_map('strtolower', (array) config('ai.database.denied_tables', []));

        return in_array($table, $denied, true);
    }

    public function canAccessTable(User $user, string $table): bool
    {
        $table = strtolower(trim($table));

        if ($table === '' || $this->isDeniedTable($table)) {
            return false;
        }

        if ($user->isInvestor()) {
            return in_array($table, $this->investorScopedTables(), true)
                || in_array($table, ['currencies'], true);
        }

        $permission = $this->permissionForTable($table);

        if ($permission === null) {
            // Unknown application tables: only super_admin / admin with reports.view.
            return $user->isSuperAdmin()
                || ($user->isAdmin() && $user->hasPermission('reports.view'));
        }

        if (is_array($permission)) {
            foreach ($permission as $perm) {
                if (is_string($perm) && $user->hasPermission($perm)) {
                    return true;
                }
            }

            return false;
        }

        return $user->hasPermission($permission);
    }

    /**
     * @param  list<string>  $tables
     * @return list<string>
     */
    public function filterAllowedTables(User $user, array $tables): array
    {
        $allowed = [];

        foreach ($tables as $table) {
            if (! is_string($table)) {
                continue;
            }

            if ($this->canAccessTable($user, $table)) {
                $allowed[] = strtolower($table);
            }
        }

        return array_values(array_unique($allowed));
    }

    /**
     * @return list<string>
     */
    public function branchScopedTables(): array
    {
        return array_map('strtolower', (array) config('ai.database.branch_scoped_tables', []));
    }

    /**
     * @return list<string>
     */
    public function investorScopedTables(): array
    {
        return array_map('strtolower', (array) config('ai.database.investor_scoped_tables', []));
    }

    public function isBranchScopedTable(string $table): bool
    {
        return in_array(strtolower($table), $this->branchScopedTables(), true);
    }

    public function isInvestorScopedTable(string $table): bool
    {
        return in_array(strtolower($table), $this->investorScopedTables(), true);
    }

    /**
     * @return string|list<string>|null
     */
    public function permissionForTable(string $table): string|array|null
    {
        $table = strtolower($table);
        $map = (array) config('ai.database.table_permissions', []);

        if (isset($map[$table])) {
            return $map[$table];
        }

        // Prefix fallbacks for product_*, sale_*, etc.
        foreach ($map as $pattern => $permission) {
            if (! is_string($pattern)) {
                continue;
            }

            if (str_ends_with($pattern, '*')) {
                $prefix = substr($pattern, 0, -1);
                if ($prefix !== '' && str_starts_with($table, $prefix)) {
                    return $permission;
                }
            }
        }

        return null;
    }

    public function canUseDatabaseTools(User $user): bool
    {
        if (! config('ai.database.enabled', true)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $gates = (array) config('ai.database.tool_gate_permissions', [
            'reports.view',
            'sales.view',
            'products.view',
            'inventory.view',
            'purchases.view',
            'customers.view',
            'investments.view',
            'users.view',
        ]);

        foreach ($gates as $permission) {
            if (is_string($permission) && $user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
