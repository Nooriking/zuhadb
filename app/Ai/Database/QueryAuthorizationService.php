<?php

namespace App\Ai\Database;

use App\Models\Branch;
use App\Models\User;

/**
 * Enforces table permissions, branch scope, and investor scope on validated SQL.
 *
 * Limitation: branch/investor row filters are enforced via required literal predicates
 * in the SQL text (fail closed). Complex obfuscation that bypasses the literal check
 * is rejected when detected; otherwise prefer denying unsafe queries.
 */
final class QueryAuthorizationService
{
    public function __construct(
        private readonly TableAccessPolicy $accessPolicy,
    ) {}

    /**
     * @param  array{tables: list<string>}  $validated
     * @return array{ok: bool, error?: string, message?: string, allowed_branch_ids?: list<int>}
     */
    public function authorize(User $user, array $validated, string $sql): array
    {
        $tables = $validated['tables'] ?? [];

        if ($tables === []) {
            return [
                'ok' => false,
                'error' => 'no_tables',
                'message' => 'Query must reference at least one application table.',
            ];
        }

        foreach ($tables as $table) {
            $base = $this->unqualify($table);

            if ($this->accessPolicy->isDeniedTable($base)) {
                return [
                    'ok' => false,
                    'error' => 'table_denied',
                    'message' => "Table [{$base}] is not available for AI queries.",
                ];
            }

            if (! $this->accessPolicy->canAccessTable($user, $base)) {
                return [
                    'ok' => false,
                    'error' => 'unauthorized_table',
                    'message' => "You do not have permission to query table [{$base}].",
                ];
            }
        }

        if ($user->isInvestor()) {
            return $this->authorizeInvestor($user, $tables, $sql);
        }

        if ($user->isBranchScoped()) {
            return $this->authorizeBranchScoped($user, $tables, $sql);
        }

        return ['ok' => true, 'allowed_branch_ids' => []];
    }

    /**
     * @param  list<string>  $tables
     * @return array{ok: bool, error?: string, message?: string, allowed_branch_ids?: list<int>}
     */
    private function authorizeBranchScoped(User $user, array $tables, string $sql): array
    {
        $branchId = (int) $user->branch_id;
        if ($branchId <= 0) {
            return [
                'ok' => false,
                'error' => 'no_branch',
                'message' => 'Branch-scoped users must be assigned to a branch to run database queries.',
            ];
        }

        $allowed = Branch::query()->visibleTo($user)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($allowed === []) {
            return [
                'ok' => false,
                'error' => 'no_authorized_branches',
                'message' => 'No authorized branches are available for database queries.',
            ];
        }

        $needsBranchFilter = false;
        foreach ($tables as $table) {
            if ($this->accessPolicy->isBranchScopedTable($this->unqualify($table))) {
                $needsBranchFilter = true;
                break;
            }
        }

        if (! $needsBranchFilter) {
            return ['ok' => true, 'allowed_branch_ids' => $allowed];
        }

        if ($this->sqlReferencesForeignBranchId($sql, $allowed)) {
            return [
                'ok' => false,
                'error' => 'unauthorized_branch',
                'message' => 'Query references a branch_id outside your authorized branches.',
                'allowed_branch_ids' => $allowed,
            ];
        }

        if (! $this->sqlHasAllowedBranchPredicate($sql, $allowed)) {
            return [
                'ok' => false,
                'error' => 'branch_filter_required',
                'message' => 'Branch-scoped queries must include an explicit filter such as '
                    .'branch_id = '.$branchId.' (your authorized branch).',
                'allowed_branch_ids' => $allowed,
            ];
        }

        return ['ok' => true, 'allowed_branch_ids' => $allowed];
    }

    /**
     * @param  list<string>  $tables
     * @return array{ok: bool, error?: string, message?: string}
     */
    private function authorizeInvestor(User $user, array $tables, string $sql): array
    {
        foreach ($tables as $table) {
            $base = $this->unqualify($table);
            if (! $this->accessPolicy->isInvestorScopedTable($base) && $base !== 'currencies') {
                return [
                    'ok' => false,
                    'error' => 'investor_table_denied',
                    'message' => "Investors may only query their own investment-related tables, not [{$base}].",
                ];
            }
        }

        $needsInvestorFilter = false;
        foreach ($tables as $table) {
            if ($this->accessPolicy->isInvestorScopedTable($this->unqualify($table))) {
                $needsInvestorFilter = true;
                break;
            }
        }

        if (! $needsInvestorFilter) {
            return ['ok' => true];
        }

        $investorId = (int) $user->id;
        if (! preg_match('/\binvestor_id\b\s*=\s*'.$investorId.'\b/i', $sql)
            && ! preg_match('/\binvestor_id\b\s+IN\s*\(\s*'.$investorId.'\s*\)/i', $sql)
        ) {
            return [
                'ok' => false,
                'error' => 'investor_filter_required',
                'message' => 'Investor queries must include investor_id = '.$investorId.' (your user id).',
            ];
        }

        if (preg_match_all('/\binvestor_id\b\s*=\s*(\d+)\b/i', $sql, $matches)) {
            foreach ($matches[1] as $id) {
                if ((int) $id !== $investorId) {
                    return [
                        'ok' => false,
                        'error' => 'unauthorized_investor',
                        'message' => 'Query references another investor_id.',
                    ];
                }
            }
        }

        return ['ok' => true];
    }

    /**
     * @param  list<int>  $allowed
     */
    private function sqlHasAllowedBranchPredicate(string $sql, array $allowed): bool
    {
        foreach ($allowed as $branchId) {
            if (preg_match('/\bbranch_id\b\s*=\s*'.$branchId.'\b/i', $sql) === 1) {
                return true;
            }

            if (preg_match('/\bbranch_id\b\s+IN\s*\(\s*'.$branchId.'\s*\)/i', $sql) === 1) {
                return true;
            }
        }

        // branch_id IN (a, b) where all are allowed
        if (preg_match('/\bbranch_id\b\s+IN\s*\(([^)]+)\)/i', $sql, $m) === 1) {
            $parts = preg_split('/\s*,\s*/', trim($m[1])) ?: [];
            $ids = [];
            foreach ($parts as $part) {
                if (! preg_match('/^\d+$/', $part)) {
                    return false;
                }
                $ids[] = (int) $part;
            }

            if ($ids === []) {
                return false;
            }

            foreach ($ids as $id) {
                if (! in_array($id, $allowed, true)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @param  list<int>  $allowed
     */
    private function sqlReferencesForeignBranchId(string $sql, array $allowed): bool
    {
        if (preg_match_all('/\bbranch_id\b\s*=\s*(\d+)\b/i', $sql, $matches)) {
            foreach ($matches[1] as $id) {
                if (! in_array((int) $id, $allowed, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function unqualify(string $table): string
    {
        $table = strtolower(trim($table));
        if (str_contains($table, '.')) {
            return (string) substr($table, strrpos($table, '.') + 1);
        }

        return $table;
    }
}
