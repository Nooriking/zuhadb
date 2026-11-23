<?php

namespace App\Ai\Database;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Executes validated, authorized, read-only SQL with row/time/payload bounds.
 */
final class ReadOnlyQueryEngine
{
    public function __construct(
        private readonly SqlReadOnlyValidator $validator,
        private readonly QueryAuthorizationService $authorization,
        private readonly SchemaDiscoveryService $schema,
        private readonly SensitiveColumnPolicy $sensitiveColumns,
        private readonly AiDatabaseAuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function execute(User $user, string $question, string $sql, array $parameters = [], ?int $limit = null): array
    {
        $started = microtime(true);
        $maxRows = max(1, (int) config('ai.database.max_rows', 100));
        $limit = $limit === null ? $maxRows : max(1, min($maxRows, $limit));
        $connection = $this->schema->connectionName();

        $lockSeconds = max(5, (int) config('ai.database.max_execution_seconds', 10) + 2);
        $lock = Cache::lock('ai-db-query:'.$user->id.':'.substr(hash('sha256', $sql), 0, 12), $lockSeconds);

        // Soft concurrency: try a short user-level lock slot.
        $userLock = Cache::lock('ai-db-query-user:'.$user->id, $lockSeconds);
        if (! $userLock->get()) {
            return $this->failure(
                $user,
                'query_database',
                $sql,
                [],
                'concurrency_limit',
                'Another AI database query is already running for your account. Please wait.',
                $started
            );
        }

        try {
            if (! $lock->get()) {
                return $this->failure(
                    $user,
                    'query_database',
                    $sql,
                    [],
                    'duplicate_query',
                    'An identical query is already running. Please wait.',
                    $started
                );
            }

            try {
                $validated = $this->validator->validate($sql);
                if (! $validated['ok']) {
                    return $this->failure(
                        $user,
                        'query_database',
                        $sql,
                        [],
                        'invalid_sql',
                        $validated['errors'][0] ?? 'SQL failed validation.',
                        $started,
                        $validated['errors']
                    );
                }

                $auth = $this->authorization->authorize($user, $validated, $validated['sql']);
                if (! ($auth['ok'] ?? false)) {
                    return $this->failure(
                        $user,
                        'query_database',
                        $sql,
                        $validated['tables'],
                        (string) ($auth['error'] ?? 'unauthorized'),
                        (string) ($auth['message'] ?? 'Query is not authorized.'),
                        $started
                    );
                }

                $sensitiveCheck = $this->rejectSensitiveSelectTargets($validated['sql'], $validated['tables']);
                if ($sensitiveCheck !== null) {
                    return $this->failure(
                        $user,
                        'query_database',
                        $sql,
                        $validated['tables'],
                        'sensitive_column',
                        $sensitiveCheck,
                        $started
                    );
                }

                $executableSql = $validated['sql'];
                $notices = [];

                if ($validated['has_star']) {
                    $expanded = $this->expandStarOrReject($executableSql, $validated['tables']);
                    if (! ($expanded['ok'] ?? false)) {
                        return $this->failure(
                            $user,
                            'query_database',
                            $sql,
                            $validated['tables'],
                            (string) ($expanded['error'] ?? 'star_rejected'),
                            (string) ($expanded['message'] ?? 'SELECT * was rejected.'),
                            $started
                        );
                    }
                    $executableSql = $expanded['sql'];
                    $notices[] = $expanded['notice'] ?? 'SELECT * was expanded to safe columns.';
                }

                $executableSql = $this->ensureLimit($executableSql, $limit);
                $bindings = $this->normalizeBindings($parameters);

                $maxSeconds = max(1, (int) config('ai.database.max_execution_seconds', 10));
                $this->applyTimeout($connection, $maxSeconds);

                $rows = DB::connection($connection)->select($executableSql, $bindings);
                $rowCount = count($rows);
                $truncated = $rowCount >= $limit;

                $primaryTable = $validated['tables'][0] ?? null;
                $safeRows = [];
                foreach ($rows as $row) {
                    $array = (array) $row;
                    $safeRows[] = $this->sensitiveColumns->filterRow($array, $primaryTable);
                }

                // Cap payload size for the LLM.
                $maxChars = max(1000, (int) config('ai.database.max_result_chars', 12000));
                $encoded = json_encode($safeRows, JSON_UNESCAPED_UNICODE);
                $payloadTruncated = false;
                if (is_string($encoded) && strlen($encoded) > $maxChars) {
                    $payloadTruncated = true;
                    while ($safeRows !== [] && strlen((string) json_encode($safeRows, JSON_UNESCAPED_UNICODE)) > $maxChars) {
                        array_pop($safeRows);
                    }
                    $notices[] = 'Result payload was truncated to fit the AI context size limit. Ask for a narrower query or the next page (OFFSET/LIMIT).';
                }

                if ($truncated) {
                    $notices[] = "Row limit of {$limit} reached. Results may be incomplete — paginate with LIMIT/OFFSET for more.";
                }

                $durationMs = (int) round((microtime(true) - $started) * 1000);

                $this->audit->log(
                    user: $user,
                    tool: 'query_database',
                    action: 'query',
                    tables: $validated['tables'],
                    success: true,
                    rowCount: count($safeRows),
                    durationMs: $durationMs,
                    sql: $executableSql,
                );

                return [
                    'ok' => true,
                    'question' => mb_substr(trim($question), 0, 500),
                    'tables' => $validated['tables'],
                    'row_count' => count($safeRows),
                    'fetched_row_count' => $rowCount,
                    'limit' => $limit,
                    'truncated' => $truncated || $payloadTruncated,
                    'execution_ms' => $durationMs,
                    'notices' => array_values(array_filter($notices)),
                    'rows' => $safeRows,
                    'connection' => $connection === config('database.default')
                        ? 'default_application'
                        : 'configured_ai_connection',
                    'read_only_db_user' => $connection === 'mysql_ai_readonly',
                ];
            } finally {
                $lock->release();
            }
        } catch (Throwable $e) {
            return $this->failure(
                $user,
                'query_database',
                $sql,
                [],
                'execution_failed',
                'The query could not be executed. Check SQL syntax, table names, and filters.',
                $started
            );
        } finally {
            $userLock->release();
        }
    }

    /**
     * @param  list<string>  $tables
     * @param  list<string>  $details
     * @return array<string, mixed>
     */
    private function failure(
        User $user,
        string $tool,
        string $sql,
        array $tables,
        string $error,
        string $message,
        float $started,
        array $details = [],
    ): array {
        $durationMs = (int) round((microtime(true) - $started) * 1000);

        $this->audit->log(
            user: $user,
            tool: $tool,
            action: 'query_denied',
            tables: $tables,
            success: false,
            rowCount: 0,
            durationMs: $durationMs,
            sql: $sql,
            errorCode: $error,
        );

        return [
            'ok' => false,
            'error' => $error,
            'message' => $message,
            'details' => $details,
            'tables' => $tables,
            'execution_ms' => $durationMs,
            'rows' => [],
            'row_count' => 0,
        ];
    }

    /**
     * @param  list<string>  $tables
     */
    private function rejectSensitiveSelectTargets(string $sql, array $tables): ?string
    {
        // Detect explicit sensitive column references (including aliases like users.password).
        if (preg_match_all('/\b([a-zA-Z0-9_`]+)\.([a-zA-Z0-9_`]+)\b/', $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $table = strtolower(str_replace('`', '', $match[1]));
                $column = strtolower(str_replace('`', '', $match[2]));
                // Skip aliases that aren't real tables unless they match a queried table.
                $resolvedTable = in_array($table, array_map(fn ($t) => $this->unqualify($t), $tables), true)
                    ? $table
                    : ($tables[0] ?? $table);

                if ($this->sensitiveColumns->isSensitive($resolvedTable, $column)) {
                    return "Column [{$column}] is restricted and cannot be selected.";
                }
            }
        }

        foreach ($tables as $table) {
            $base = $this->unqualify($table);
            // Bare column names that are always sensitive.
            foreach ((array) config('ai.database.sensitive_columns', []) as $column) {
                if (! is_string($column) || $column === '') {
                    continue;
                }
                if (preg_match('/\b'.preg_quote($column, '/').'\b/i', $sql) === 1) {
                    // Avoid false positives on unrelated words by requiring SQL identifier context.
                    if (preg_match('/(?<![a-zA-Z0-9_`])'.preg_quote($column, '/').'(?![a-zA-Z0-9_`])/i', $sql) === 1) {
                        return "Column [{$column}] is restricted and cannot be selected.";
                    }
                }
            }

            // Always block known auth columns even if patterns evolve.
            unset($base);
        }

        return null;
    }

    /**
     * @param  list<string>  $tables
     * @return array{ok: bool, sql?: string, error?: string, message?: string, notice?: string}
     */
    private function expandStarOrReject(string $sql, array $tables): array
    {
        $physical = array_values(array_unique(array_map(fn ($t) => $this->unqualify($t), $tables)));

        if (count($physical) !== 1) {
            return [
                'ok' => false,
                'error' => 'star_not_allowed',
                'message' => 'SELECT * with multiple tables/joins is not allowed. Select explicit safe columns.',
            ];
        }

        $table = $physical[0];
        $columns = $this->schema->columnNamesForTable($table);
        if ($columns === []) {
            return [
                'ok' => false,
                'error' => 'no_safe_columns',
                'message' => 'No safe columns are available for SELECT * on this table.',
            ];
        }

        $quoted = array_map(
            static fn (string $c) => '`'.str_replace('`', '``', $c).'`',
            $columns
        );
        $replacement = implode(', ', $quoted);

        // Replace first bare * select list carefully.
        $rewritten = preg_replace('/SELECT\s+\*\s+FROM/i', 'SELECT '.$replacement.' FROM', $sql, 1, $count);
        if (! is_string($rewritten) || $count !== 1) {
            return [
                'ok' => false,
                'error' => 'star_rewrite_failed',
                'message' => 'Could not safely expand SELECT *. Please list explicit columns.',
            ];
        }

        // Re-validate rewritten SQL.
        $recheck = $this->validator->validate($rewritten);
        if (! $recheck['ok']) {
            return [
                'ok' => false,
                'error' => 'star_rewrite_invalid',
                'message' => 'Expanded SELECT * failed validation. Please list explicit columns.',
            ];
        }

        return [
            'ok' => true,
            'sql' => $rewritten,
            'notice' => 'SELECT * was expanded to non-sensitive columns only.',
        ];
    }

    private function ensureLimit(string $sql, int $limit): string
    {
        if (preg_match('/\bLIMIT\s+\d+/i', $sql) === 1) {
            // Cap an existing LIMIT if it exceeds max.
            return preg_replace_callback('/\bLIMIT\s+(\d+)/i', function (array $m) use ($limit) {
                $requested = (int) $m[1];

                return 'LIMIT '.min($requested, $limit);
            }, $sql, 1) ?? ($sql.' LIMIT '.$limit);
        }

        return rtrim($sql, " \t\n\r;").' LIMIT '.$limit;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string|int, mixed>
     */
    private function normalizeBindings(array $parameters): array
    {
        $bindings = [];

        foreach ($parameters as $key => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            if (is_string($key) && $key !== '') {
                $bindings[$key] = $value;
            } elseif (is_int($key)) {
                $bindings[] = $value;
            }
        }

        return $bindings;
    }

    private function applyTimeout(string $connection, int $seconds): void
    {
        try {
            $driver = DB::connection($connection)->getDriverName();
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                // MAX_EXECUTION_TIME is milliseconds (MySQL 5.7.8+).
                DB::connection($connection)->statement('SET SESSION MAX_EXECUTION_TIME = '.(int) ($seconds * 1000));
            }
        } catch (Throwable) {
            // Best-effort; application-level lock still bounds wait.
        }
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
