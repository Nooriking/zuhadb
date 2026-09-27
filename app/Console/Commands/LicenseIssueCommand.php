<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;

class LicenseIssueCommand extends Command
{
    protected $signature = 'license:issue
        {machine_id : Client machine id from license:machine-id}
        {--customer=Licensed shop : Customer / shop name}
        {--days= : Optional expiry in days (empty = lifetime)}';

    protected $description = 'VENDOR ONLY: issue a signed license key for a client machine id';

    public function handle(LicenseService $licenses): int
    {
        if (! config('license.allow_issue')) {
            $this->error('Issuing is disabled on this install (LICENSE_ALLOW_ISSUE=false).');
            $this->comment('Only your vendor PC should have LICENSE_ALLOW_ISSUE=true + LICENSE_SECRET.');

            return self::FAILURE;
        }

        try {
            $days = $this->option('days');
            $issued = $licenses->issue(
                machineId: (string) $this->argument('machine_id'),
                customer: (string) $this->option('customer'),
                days: filled($days) ? (int) $days : null,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('License key (give only this to the client):');
        $this->newLine();
        $this->line($issued['key']);
        $this->newLine();
        $this->table(
            ['Field', 'Value'],
            collect($issued['payload'])
                ->except('signature')
                ->map(fn ($v, $k) => [$k, is_null($v) ? '—' : (string) $v])
                ->values()
                ->all()
        );
        $this->warn('Keep LICENSE_SECRET private. Never put LICENSE_ALLOW_ISSUE=true on the client PC.');

        return self::SUCCESS;
    }
}
