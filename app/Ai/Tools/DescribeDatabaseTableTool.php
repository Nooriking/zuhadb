<?php

namespace App\Ai\Tools;

use App\Ai\Contracts\AiTool;
use App\Ai\Database\AiDatabaseAuditLogger;
use App\Ai\Database\SchemaDiscoveryService;
use App\Ai\Database\TableAccessPolicy;
use App\Models\User;

final class DescribeDatabaseTableTool implements AiTool
{
    public const NAME = 'describe_database_table';

    public function __construct(
        private readonly SchemaDiscoveryService $schema,
        private readonly TableAccessPolicy $accessPolicy,
        private readonly AiDatabaseAuditLogger $audit,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): string
    {
        return 'Describe columns, indexes, and foreign-key relationships for one application table. '
            .'Use this for schema questions (column list, field purposes, data types, keys) '
            .'such as “what columns does products have” / «جدول products چه ستون‌هایی دارد». '
            .'Sensitive columns (passwords, tokens, secrets) are omitted. '
            .'Call this before writing SQL when table structure is uncertain.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'table' => [
                    'type' => 'string',
                    'description' => 'Application table name (e.g. products, sales, users).',
                    'maxLength' => 64,
                ],
            ],
            'required' => ['table'],
            'additionalProperties' => false,
        ];
    }

    public function authorize(User $user): bool
    {
        return $this->accessPolicy->canUseDatabaseTools($user);
    }

    public function execute(User $user, array $arguments): array
    {
        $table = strtolower(trim((string) ($arguments['table'] ?? '')));
        $result = $this->schema->describeTable($user, $table);

        $this->audit->log(
            user: $user,
            tool: self::NAME,
            action: ($result['ok'] ?? false) ? 'schema_describe' : 'schema_describe_denied',
            tables: $table !== '' ? [$table] : [],
            success: (bool) ($result['ok'] ?? false),
            errorCode: isset($result['error']) ? (string) $result['error'] : null,
        );

        return $result;
    }
}
