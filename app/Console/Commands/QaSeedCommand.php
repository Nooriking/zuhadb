<?php

namespace App\Console\Commands;

use Database\Seeders\QaCompleteWorkflowSeeder;
use Illuminate\Console\Command;

/**
 * Seeds the complete QA workflow against an allow-listed database only (default: zuha_testing).
 */
class QaSeedCommand extends Command
{
    protected $signature = 'qa:seed
        {--database= : Override DB_DATABASE for this run (must be allow-listed)}
        {--force : Required when APP_ENV is not testing/local}';

    protected $description = 'Seed complete relational QA dataset (investors → products → purchases → sales → returns). Refuses non-QA databases.';

    public function handle(): int
    {
        if ($override = $this->option('database')) {
            config(['database.connections.'.config('database.default').'.database' => $override]);
            app('db')->purge();
            app('db')->reconnect();
        }

        $database = (string) config('database.connections.'.config('database.default').'.database');
        $env = (string) app()->environment();

        $this->info("Environment: {$env}");
        $this->info("Database:    {$database}");

        $allowed = array_values(array_filter(array_map('trim', explode(',', (string) env(
            'QA_SEED_ALLOWED_DATABASES',
            'zuha_testing'
        )))));

        if (! in_array($database, $allowed, true)) {
            $this->error("Refusing to seed. [{$database}] is not allow-listed (".implode(', ', $allowed).').');

            return self::FAILURE;
        }

        if (! app()->environment(['testing', 'local']) && ! $this->option('force')) {
            $this->error('APP_ENV must be testing/local, or pass --force after confirming this is a disposable QA database.');

            return self::FAILURE;
        }

        $this->warn('Starting QaCompleteWorkflowSeeder…');
        $this->call('db:seed', [
            '--class' => QaCompleteWorkflowSeeder::class,
            '--force' => true,
        ]);

        return self::SUCCESS;
    }
}
