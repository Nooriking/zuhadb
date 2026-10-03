<?php

namespace App\Console\Commands;

use App\Models\Branch;
use Illuminate\Console\Command;

class EnsureDefaultBranch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'branch:ensure
                            {--force : Force creation even if branches exist}
                            {--name=Kabul — Main : Branch name}
                            {--code=KBL : Branch code}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensure at least one default branch exists in the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Checking for existing branches...');

        // Count active (non-trashed) branches
        $count = Branch::query()->notTrashed()->count();

        if ($count > 0 && ! $this->option('force')) {
            $this->info("✅ {$count} branch(es) already exist. No action needed.");

            $this->table(
                ['ID', 'Name', 'Code', 'Status', 'Trashed'],
                Branch::query()
                    ->notTrashed()
                    ->select('id', 'name', 'code', 'status', 'trash')
                    ->get()
                    ->toArray()
            );

            return self::SUCCESS;
        }

        // Create default branch
        $name = $this->option('name');
        $code = $this->option('code');

        $branch = Branch::firstOrCreate(
            ['id' => 1],
            [
                'name'   => $name,
                'code'   => $code,
                'status' => 'active',
                'trash'  => false,
            ]
        );

        if ($branch->wasRecentlyCreated) {
            $this->info('✅ Default branch created successfully:');
        } else {
            $this->info('✅ Default branch already existed:');
        }

        $this->table(
            ['ID', 'Name', 'Code', 'Status'],
            [[$branch->id, $branch->name, $branch->code, $branch->status]]
        );

        return self::SUCCESS;
    }
}