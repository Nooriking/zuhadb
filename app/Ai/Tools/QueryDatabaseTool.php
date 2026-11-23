<?php

namespace App\Ai\Tools;

use App\Ai\Contracts\AiTool;
use App\Ai\Database\ReadOnlyQueryEngine;
use App\Ai\Database\TableAccessPolicy;
use App\Models\User;

final class QueryDatabaseTool implements AiTool
{
    public const NAME = 'query_database';

    public function __construct(
        private readonly ReadOnlyQueryEngine $engine,
        private readonly TableAccessPolicy $accessPolicy,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): string
    {
        return 'Execute a secure read-only SELECT (or WITH … SELECT) against the application database. '
            .'Always inspect schema with list_database_tables / describe_database_table when unsure. '
            .'Only SELECT/aggregates/joins are allowed. Never invent results. '
            .'Branch-scoped users must filter branch_id to their branch. '
            .'Investors must filter investor_id to their user id. '
            .'Results are limited (default/max 100 rows) — paginate with LIMIT/OFFSET when needed.';
    }

    public function parametersSchema(): array
    {
        $maxRows = max(1, (int) config('ai.database.max_rows', 100));

        return [
            'type' => 'object',
            'properties' => [
                'question' => [
                    'type' => 'string',
                    'description' => 'Natural-language purpose of this query (for audit/context).',
                    'maxLength' => 500,
                ],
                'sql' => [
                    'type' => 'string',
                    'description' => 'A single read-only SELECT or WITH … SELECT statement.',
                    'maxLength' => max(100, (int) config('ai.database.max_sql_length', 5000)),
                ],
                'parameters' => [
                    'type' => 'object',
                    'description' => 'Optional bound parameters for placeholders (? or :name).',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => "Maximum rows to return (1–{$maxRows}).",
                    'minimum' => 1,
                    'maximum' => $maxRows,
                ],
            ],
            'required' => ['question', 'sql'],
            'additionalProperties' => false,
        ];
    }

    public function authorize(User $user): bool
    {
        return $this->accessPolicy->canUseDatabaseTools($user);
    }

    public function execute(User $user, array $arguments): array
    {
        $question = trim((string) ($arguments['question'] ?? ''));
        $sql = trim((string) ($arguments['sql'] ?? ''));
        $parameters = is_array($arguments['parameters'] ?? null) ? $arguments['parameters'] : [];
        $limit = array_key_exists('limit', $arguments) && $arguments['limit'] !== null
            ? (int) $arguments['limit']
            : null;

        if ($question === '' || $sql === '') {
            return [
                'ok' => false,
                'error' => 'invalid_arguments',
                'message' => 'Both question and sql are required.',
                'rows' => [],
                'row_count' => 0,
            ];
        }

        return $this->engine->execute($user, $question, $sql, $parameters, $limit);
    }
}
