<?php

namespace App\Ai\Database;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Log;

/**
 * Safe audit trail for AI database intelligence tools — never logs secrets or row payloads.
 */
final class AiDatabaseAuditLogger
{
    /**
     * @param  list<string>  $tables
     * @param  array<string, mixed>  $extra
     */
    public function log(
        User $user,
        string $tool,
        string $action,
        array $tables = [],
        bool $success = true,
        ?int $rowCount = null,
        ?int $durationMs = null,
        ?string $sql = null,
        ?string $errorCode = null,
        array $extra = [],
    ): void {
        $properties = array_filter([
            'tool' => $tool,
            'tables' => array_values(array_unique(array_map('strtolower', $tables))),
            'success' => $success,
            'row_count' => $rowCount,
            'duration_ms' => $durationMs,
            'sql_hash' => is_string($sql) && $sql !== '' ? hash('sha256', $sql) : null,
            'error_code' => $errorCode,
            ...$extra,
        ], static fn ($v) => $v !== null);

        ActivityLogger::log(
            action: $action,
            section: 'ai_database',
            description: sprintf('AI database tool [%s] %s', $tool, $success ? 'ok' : 'denied_or_failed'),
            properties: $properties,
            userId: $user->id,
        );

        Log::info('ai.database.audit', [
            'user_id' => $user->id,
            'tool' => $tool,
            'action' => $action,
            'success' => $success,
            'tables' => $properties['tables'] ?? [],
            'row_count' => $rowCount,
            'duration_ms' => $durationMs,
            'sql_hash' => $properties['sql_hash'] ?? null,
            'error_code' => $errorCode,
        ]);
    }
}
