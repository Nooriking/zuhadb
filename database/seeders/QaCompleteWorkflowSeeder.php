<?php

namespace Database\Seeders;

use App\Models\Setting;
use Database\Seeders\Qa\Concerns\GuardsQaDatabase;
use Database\Seeders\Qa\Concerns\QaContext;
use Database\Seeders\Qa\QaCurrencyExchangeSeeder;
use Database\Seeders\Qa\QaFoundationSeeder;
use Database\Seeders\Qa\QaInvestorSeeder;
use Database\Seeders\Qa\QaProductCatalogSeeder;
use Database\Seeders\Qa\QaPurchaseInventorySeeder;
use Database\Seeders\Qa\QaReconciliationSeeder;
use Database\Seeders\Qa\QaReturnWithdrawalSeeder;
use Database\Seeders\Qa\QaSaleWorkflowSeeder;
use Database\Seeders\Qa\QaSupportingModulesSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

/**
 * Complete relational QA dataset for zuha_testing only.
 *
 * Run:
 *   php artisan qa:seed --database=zuha_testing
 *
 * Idempotent: re-running skips markers that already exist.
 * Does NOT replace DatabaseSeeder (production-safe handoff remains SuperAdmin + settings only).
 */
class QaCompleteWorkflowSeeder extends Seeder
{
    use GuardsQaDatabase;

    public function run(): void
    {
        $this->assertQaDatabase();

        $ctx = new QaContext;
        App::instance(QaContext::class, $ctx);

        $this->command?->info('=== ZUHA QA Complete Workflow Seeder ===');

        $this->seedWithContext($ctx, [
            QaCurrencyExchangeSeeder::class,
            QaFoundationSeeder::class,
            QaInvestorSeeder::class,
            QaProductCatalogSeeder::class,
            QaPurchaseInventorySeeder::class,
            QaSaleWorkflowSeeder::class,
            QaReturnWithdrawalSeeder::class,
            QaSupportingModulesSeeder::class,
            QaReconciliationSeeder::class,
        ]);

        $this->markComplete();
        $this->command?->info('=== QA Complete Workflow finished ===');
    }

    /**
     * @param  list<class-string<Seeder>>  $seeders
     */
    private function seedWithContext(QaContext $ctx, array $seeders): void
    {
        foreach ($seeders as $class) {
            /** @var Seeder $seeder */
            $seeder = App::make($class, ['ctx' => $ctx]);
            $seeder->setCommand($this->command);
            $this->command?->warn("→ {$class}");
            $seeder->run();
        }
    }

    private function markComplete(): void
    {
        try {
            Setting::query()->updateOrCreate(
                ['key' => $this->qaMarkerKey()],
                [
                    'value' => now()->toIso8601String(),
                    'type' => 'string',
                    'group' => 'general',
                    'description' => 'QA complete workflow last seeded at',
                    'is_public' => false,
                ]
            );
        } catch (\Throwable $e) {
            $this->command?->warn('Could not write QA marker setting: '.$e->getMessage());
        }
    }
}
