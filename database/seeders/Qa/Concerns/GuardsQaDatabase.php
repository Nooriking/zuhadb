<?php

namespace Database\Seeders\Qa\Concerns;

use RuntimeException;

/**
 * Hard stop unless the active connection is the isolated QA/testing database.
 */
trait GuardsQaDatabase
{
    protected function assertQaDatabase(): void
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');
        $allowed = array_values(array_filter(array_map('trim', explode(',', (string) env(
            'QA_SEED_ALLOWED_DATABASES',
            'zuha_testing'
        )))));

        if ($database === '' || ! in_array($database, $allowed, true)) {
            throw new RuntimeException(
                "QA seeders refused to run. Active database [{$database}] is not in the allow-list ["
                .implode(', ', $allowed)
                .']. Set DB_DATABASE=zuha_testing (or QA_SEED_ALLOWED_DATABASES) before seeding.'
            );
        }

        $this->command?->info("QA database guard OK: {$database}");
    }

    protected function qaMarkerKey(): string
    {
        return 'qa_complete_workflow_seeded_at';
    }
}
