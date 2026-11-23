<?php

namespace App\Ai\Database;

/**
 * Identifies and strips authentication / secret columns from schema and query results.
 */
final class SensitiveColumnPolicy
{
    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    public function safeColumns(string $table, array $columns): array
    {
        $safe = [];

        foreach ($columns as $column) {
            if (! is_string($column) || $column === '') {
                continue;
            }

            if ($this->isSensitive($table, $column)) {
                continue;
            }

            $safe[] = $column;
        }

        return array_values($safe);
    }

    public function isSensitive(string $table, string $column): bool
    {
        $column = strtolower(trim($column));
        $table = strtolower(trim($table));

        if ($column === '') {
            return true;
        }

        $exact = array_map('strtolower', (array) config('ai.database.sensitive_columns', []));
        if (in_array($column, $exact, true)) {
            return true;
        }

        foreach ((array) config('ai.database.sensitive_column_patterns', []) as $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                continue;
            }

            if (@preg_match($pattern, $column) === 1) {
                return true;
            }
        }

        // settings.value can hold secrets depending on key — never expose via star/select of value without allowlist.
        if ($table === 'settings' && in_array($column, ['value'], true)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function filterRow(array $row, ?string $primaryTable = null): array
    {
        $filtered = [];

        foreach ($row as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $base = $key;
            if (str_contains($key, '.')) {
                $parts = explode('.', $key);
                $base = (string) end($parts);
            }

            if ($this->isSensitive((string) $primaryTable, $base)) {
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function filterRows(array $rows, ?string $primaryTable = null): array
    {
        return array_map(
            fn (array $row) => $this->filterRow($row, $primaryTable),
            $rows
        );
    }
}
