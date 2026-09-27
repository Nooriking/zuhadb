<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;

class LicenseStatusCommand extends Command
{
    protected $signature = 'license:status';

    protected $description = 'Show license lock status for this computer';

    public function handle(LicenseService $licenses): int
    {
        $status = $licenses->status();

        $this->table(
            ['Field', 'Value'],
            [
                ['enabled', $status['enabled'] ? 'yes' : 'no'],
                ['valid', $status['valid'] ? 'yes' : 'no'],
                ['machine_id', $status['machine_id']],
                ['customer', $status['customer'] ?? '—'],
                ['issued_at', $status['issued_at'] ?? '—'],
                ['expires_at', $status['expires_at'] ?? 'lifetime'],
                ['error', $status['error'] ?? '—'],
            ]
        );

        return $status['enabled'] && ! $status['valid'] ? self::FAILURE : self::SUCCESS;
    }
}
