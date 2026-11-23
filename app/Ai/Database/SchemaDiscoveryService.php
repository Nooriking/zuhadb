<?php

namespace App\Ai\Database;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Discovers application schema metadata from MySQL/MariaDB information_schema.
 * Always scoped to the configured connection database — never other schemas.
 */
final class SchemaDiscoveryService
{
    public function __construct(
        private readonly TableAccessPolicy $accessPolicy,
        private readonly SensitiveColumnPolicy $sensitiveColumns,
    ) {}

    public function connectionName(): string
    {
        $configured = config('ai.database.connection');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return (string) config('database.default');
    }

    public function databaseName(): string
    {
        return (string) DB::connection($this->connectionName())->getDatabaseName();
    }

    /**
     * @return array{
     *     database: string,
     *     tables: list<array{name: string, engine: string|null, approx_rows: int|null, comment: string|null}>,
     *     columns: array<string, list<array{name: string, type: string, nullable: bool, key: string|null, default: mixed}>>,
     *     indexes: array<string, list<array{name: string, columns: list<string>, unique: bool}>>,
     *     foreign_keys: list<array{table: string, column: string, referenced_table: string, referenced_column: string, constraint: string}>,
     *     restricted_columns_omitted: array<string, int>
     * }
     */
    public function snapshot(bool $fresh = false): array
    {
        $db = $this->databaseName();
        $cacheKey = 'ai.db.schema.'.$db;
        $ttl = max(60, (int) config('ai.database.schema_cache_ttl', 600));

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, $ttl, function () use ($db) {
            return $this->buildSnapshot($db);
        });
    }

    public function forgetCache(): void
    {
        Cache::forget('ai.db.schema.'.$this->databaseName());
    }

    /**
     * @return list<array{name: string, engine: string|null, approx_rows: int|null, comment: string|null}>
     */
    public function listTables(User $user): array
    {
        $snapshot = $this->snapshot();
        $allowed = [];

        foreach ($snapshot['tables'] as $table) {
            if ($this->accessPolicy->canAccessTable($user, $table['name'])) {
                $allowed[] = $table;
            }
        }

        return $allowed;
    }

    /**
     * @return array<string, mixed>
     */
    public function describeTable(User $user, string $table): array
    {
        $table = strtolower(trim($table));

        if ($table === '' || $this->accessPolicy->isDeniedTable($table)) {
            return [
                'ok' => false,
                'error' => 'table_not_allowed',
                'message' => 'That table is not available for AI schema inspection.',
            ];
        }

        if (! $this->accessPolicy->canAccessTable($user, $table)) {
            return [
                'ok' => false,
                'error' => 'unauthorized_table',
                'message' => 'You do not have permission to inspect that table.',
            ];
        }

        $snapshot = $this->snapshot();
        $meta = null;

        foreach ($snapshot['tables'] as $row) {
            if ($row['name'] === $table) {
                $meta = $row;
                break;
            }
        }

        if ($meta === null) {
            return [
                'ok' => false,
                'error' => 'table_not_found',
                'message' => 'No such application table in the current database.',
            ];
        }

        $columns = $snapshot['columns'][$table] ?? [];
        $indexes = $snapshot['indexes'][$table] ?? [];
        $fks = array_values(array_filter(
            $snapshot['foreign_keys'],
            fn (array $fk) => $fk['table'] === $table || $fk['referenced_table'] === $table
        ));

        return [
            'ok' => true,
            'database' => $snapshot['database'],
            'table' => $meta,
            'columns' => $columns,
            'indexes' => $indexes,
            'relationships' => $fks,
            'restricted_columns_omitted' => $snapshot['restricted_columns_omitted'][$table] ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getRelationships(User $user): array
    {
        $snapshot = $this->snapshot();
        $edges = [];

        foreach ($snapshot['foreign_keys'] as $fk) {
            if (
                $this->accessPolicy->canAccessTable($user, $fk['table'])
                && $this->accessPolicy->canAccessTable($user, $fk['referenced_table'])
            ) {
                $edges[] = $fk;
            }
        }

        return [
            'ok' => true,
            'database' => $snapshot['database'],
            'relationships' => $edges,
            'count' => count($edges),
        ];
    }

    /**
     * @return list<string>
     */
    public function allApplicationTableNames(): array
    {
        return array_map(
            static fn (array $t) => $t['name'],
            $this->snapshot()['tables']
        );
    }

    /**
     * @return list<string>
     */
    public function columnNamesForTable(string $table): array
    {
        $table = strtolower($table);
        $snapshot = $this->snapshot();
        $columns = $snapshot['columns'][$table] ?? [];

        return array_map(static fn (array $c) => $c['name'], $columns);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(string $database): array
    {
        $denied = array_map('strtolower', (array) config('ai.database.denied_tables', []));
        $systemSchemas = array_map('strtolower', (array) config('ai.database.deny_system_schemas', [
            'information_schema', 'mysql', 'performance_schema', 'sys',
        ]));

        if (in_array(strtolower($database), $systemSchemas, true)) {
            return [
                'database' => $database,
                'tables' => [],
                'columns' => [],
                'indexes' => [],
                'foreign_keys' => [],
                'restricted_columns_omitted' => [],
            ];
        }

        $connection = $this->connectionName();

        $tableRows = DB::connection($connection)->select(
            'SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_ROWS AS approx_rows, TABLE_COMMENT AS table_comment
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?
             ORDER BY TABLE_NAME',
            [$database, 'BASE TABLE']
        );

        $tables = [];
        $allowedNames = [];

        foreach ($tableRows as $row) {
            $name = strtolower((string) $row->name);
            if (in_array($name, $denied, true)) {
                continue;
            }

            $allowedNames[] = $name;
            $tables[] = [
                'name' => $name,
                'engine' => $row->engine !== null ? (string) $row->engine : null,
                'approx_rows' => $row->approx_rows !== null ? (int) $row->approx_rows : null,
                'comment' => ($row->table_comment ?? '') !== '' ? (string) $row->table_comment : null,
            ];
        }

        $columns = [];
        $restrictedOmitted = [];

        if ($allowedNames !== []) {
            $placeholders = implode(',', array_fill(0, count($allowedNames), '?'));
            $columnRows = DB::connection($connection)->select(
                "SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name, COLUMN_TYPE AS column_type,
                        IS_NULLABLE AS is_nullable, COLUMN_KEY AS column_key, COLUMN_DEFAULT AS column_default
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ({$placeholders})
                 ORDER BY TABLE_NAME, ORDINAL_POSITION",
                [$database, ...$allowedNames]
            );

            foreach ($columnRows as $col) {
                $table = strtolower((string) $col->table_name);
                $column = (string) $col->column_name;

                if ($this->sensitiveColumns->isSensitive($table, $column)) {
                    $restrictedOmitted[$table] = ($restrictedOmitted[$table] ?? 0) + 1;

                    continue;
                }

                $columns[$table][] = [
                    'name' => $column,
                    'type' => (string) $col->column_type,
                    'nullable' => strtoupper((string) $col->is_nullable) === 'YES',
                    'key' => ($col->column_key ?? '') !== '' ? (string) $col->column_key : null,
                    'default' => $col->column_default,
                ];
            }
        }

        $indexes = [];
        if ($allowedNames !== []) {
            $placeholders = implode(',', array_fill(0, count($allowedNames), '?'));
            try {
                $indexRows = DB::connection($connection)->select(
                    "SELECT TABLE_NAME AS table_name, INDEX_NAME AS index_name, COLUMN_NAME AS column_name,
                            NON_UNIQUE AS non_unique, SEQ_IN_INDEX AS seq
                     FROM information_schema.STATISTICS
                     WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ({$placeholders})
                     ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX",
                    [$database, ...$allowedNames]
                );

                $grouped = [];
                foreach ($indexRows as $idx) {
                    $table = strtolower((string) $idx->table_name);
                    $indexName = (string) $idx->index_name;
                    $key = $table.'|'.$indexName;
                    if (! isset($grouped[$key])) {
                        $grouped[$key] = [
                            'table' => $table,
                            'name' => $indexName,
                            'columns' => [],
                            'unique' => ((int) $idx->non_unique) === 0,
                        ];
                    }
                    $grouped[$key]['columns'][] = (string) $idx->column_name;
                }

                foreach ($grouped as $index) {
                    $table = $index['table'];
                    unset($index['table']);
                    $indexes[$table][] = $index;
                }
            } catch (Throwable) {
                $indexes = [];
            }
        }

        $foreignKeys = [];
        if ($allowedNames !== []) {
            try {
                $fkRows = DB::connection($connection)->select(
                    'SELECT kcu.TABLE_NAME AS table_name, kcu.COLUMN_NAME AS column_name,
                            kcu.REFERENCED_TABLE_NAME AS referenced_table, kcu.REFERENCED_COLUMN_NAME AS referenced_column,
                            kcu.CONSTRAINT_NAME AS constraint_name
                     FROM information_schema.KEY_COLUMN_USAGE kcu
                     WHERE kcu.TABLE_SCHEMA = ?
                       AND kcu.REFERENCED_TABLE_SCHEMA = ?
                       AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
                     ORDER BY kcu.TABLE_NAME, kcu.ORDINAL_POSITION',
                    [$database, $database]
                );

                foreach ($fkRows as $fk) {
                    $table = strtolower((string) $fk->table_name);
                    $ref = strtolower((string) $fk->referenced_table);
                    if (in_array($table, $denied, true) || in_array($ref, $denied, true)) {
                        continue;
                    }

                    if ($this->sensitiveColumns->isSensitive($table, (string) $fk->column_name)) {
                        continue;
                    }

                    $foreignKeys[] = [
                        'table' => $table,
                        'column' => (string) $fk->column_name,
                        'referenced_table' => $ref,
                        'referenced_column' => (string) $fk->referenced_column,
                        'constraint' => (string) $fk->constraint_name,
                    ];
                }
            } catch (Throwable) {
                $foreignKeys = [];
            }
        }

        return [
            'database' => $database,
            'tables' => $tables,
            'columns' => $columns,
            'indexes' => $indexes,
            'foreign_keys' => $foreignKeys,
            'restricted_columns_omitted' => $restrictedOmitted,
        ];
    }
}
