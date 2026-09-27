<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'app:backup-database {--keep=14 : Number of daily backups to retain}';

    protected $description = 'Create a database backup under storage/app/backups';

    public function handle(DatabaseBackupService $backups): int
    {
        try {
            $path = $backups->createBackup(keep: max(1, (int) $this->option('keep')));
            $this->info('Backup saved: '.$path);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
