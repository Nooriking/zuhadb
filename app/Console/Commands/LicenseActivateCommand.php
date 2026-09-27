<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;

class LicenseActivateCommand extends Command
{
    protected $signature = 'license:activate {key? : License key (or paste when prompted)}';

    protected $description = 'Activate a license key on this computer';

    public function handle(LicenseService $licenses): int
    {
        $key = (string) ($this->argument('key') ?: $this->ask('Paste license key'));

        try {
            $payload = $licenses->activate($key);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('License activated.');
        $this->line('Customer: '.($payload['customer'] ?? '—'));
        $this->line('Machine: '.($payload['machine_id'] ?? '—'));

        return self::SUCCESS;
    }
}
