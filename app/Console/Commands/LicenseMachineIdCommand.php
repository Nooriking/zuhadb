<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;

class LicenseMachineIdCommand extends Command
{
    protected $signature = 'license:machine-id';

    protected $description = 'Show this computer machine id (send it to the vendor for a license key)';

    public function handle(LicenseService $licenses): int
    {
        $id = $licenses->machineId();
        $this->info('Machine ID:');
        $this->line($id);
        $this->newLine();
        $this->comment('Send this ID to the vendor. Do not share LICENSE_SECRET with the client.');

        return self::SUCCESS;
    }
}
