<?php

namespace App\Ai\Tools;

use App\Ai\Contracts\AiTool;
use App\Ai\Database\AiDatabaseAuditLogger;
use App\Ai\Database\SchemaDiscoveryService;
use App\Ai\Database\TableAccessPolicy;
use App\Models\User;

final class GetDatabaseRelationshipsTool implements AiTool
{
    public const NAME = 'get_database_relationships';

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
        return 'Return foreign-key relationships among application tables the current user may access. '
            .'Use this to plan safe JOINs for analytical SELECT queries.';
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
        $result = $this->schema->getRelationships($user);

        $tables = [];
        foreach ($result['relationships'] ?? [] as $edge) {
            $tables[] = $edge['table'];
            $tables[] = $edge['referenced_table'];
        }

        $this->audit->log(
            user: $user,
            tool: self::NAME,
            action: 'schema_relationships',
            tables: array_values(array_unique($tables)),
            success: true,
            rowCount: (int) ($result['count'] ?? 0),
        );

        return $result;
    }
}
