<?php

namespace App\Ai\Tools;

use App\Ai\Contracts\AiTool;
use App\Ai\Database\AiDatabaseAuditLogger;
use App\Ai\Database\SchemaDiscoveryService;
use App\Ai\Database\TableAccessPolicy;
use App\Models\User;

final class ListDatabaseTablesTool implements AiTool
{
    public const NAME = 'list_database_tables';

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
        return 'List application database tables available to the current user for read-only analysis. '
            .'Use this before writing SQL when you are unsure which tables exist. '
            .'Does not return system schemas or denied infrastructure tables.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
            'additionalProperties' => false,
        ];
    }

    public function authorize(User $user): bool
    {
        return $this->accessPolicy->canUseDatabaseTools($user);
    }

    public function execute(User $user, array $arguments): array
    {
        $tables = $this->schema->listTables($user);

        $this->audit->log(
            user: $user,
            tool: self::NAME,
            action: 'schema_list',
            tables: array_map(static fn (array $t) => $t['name'], $tables),
            success: true,
            rowCount: count($tables),
        );

        return [
            'ok' => true,
            'database' => $this->schema->databaseName(),
            'count' => count($tables),
            'tables' => $tables,
            'hint' => 'Use describe_database_table for columns/relationships, then query_database with a SELECT.',
        ];
    }
}
