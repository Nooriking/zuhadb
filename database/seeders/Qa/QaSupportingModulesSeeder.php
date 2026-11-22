<?php

namespace Database\Seeders\Qa;

use App\Models\ActivityNotification;
use App\Models\Expense;
use App\Services\ActivityLogger;
use Database\Seeders\Qa\Concerns\GuardsQaDatabase;
use Database\Seeders\Qa\Concerns\QaContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class QaSupportingModulesSeeder extends Seeder
{
    use GuardsQaDatabase;

    public function __construct(private readonly QaContext $ctx) {}

    public function run(): void
    {
        $this->assertQaDatabase();
        Auth::login($this->ctx->admin);

        if (! Expense::query()->where('expense_number', 'QA-EXP-RENT-001')->exists()) {
            Expense::create([
                'branch_id' => $this->ctx->mainBranch->id,
                'expense_number' => 'QA-EXP-RENT-001',
                'title' => 'QA Shop Rent March',
                'category' => 'rent',
                'amount_afn' => 25000,
                'expense_date' => '2026-03-01',
                'payment_method' => 'cash',
                'description' => 'QA seeded rent expense',
                'created_by' => $this->ctx->admin->id,
                'user_id' => $this->ctx->admin->id,
                'trash' => false,
            ]);
        }

        if (! Expense::query()->where('expense_number', 'QA-EXP-UTIL-001')->exists()) {
            Expense::create([
                'branch_id' => $this->ctx->mainBranch->id,
                'expense_number' => 'QA-EXP-UTIL-001',
                'title' => 'QA Electricity',
                'category' => 'electricity',
                'amount_afn' => 3500,
                'expense_date' => '2026-03-05',
                'payment_method' => 'cash',
                'description' => 'QA seeded utility expense',
                'created_by' => $this->ctx->admin->id,
                'user_id' => $this->ctx->admin->id,
                'trash' => false,
            ]);
        }

        // Stock transfers require batch-aware workflow; do not invent inventory moves here.

        if (! ActivityNotification::query()->where('title', 'QA Seed Complete')->exists()) {
            ActivityNotification::create([
                'user_id' => $this->ctx->admin->id,
                'section' => 'system',
                'title' => 'QA Seed Complete',
                'body' => 'Complete factory/seeder workflow finished for zuha_testing.',
                'url' => '/dashboard',
            ]);
        }

        ActivityLogger::log(
            action: 'seed',
            section: 'system',
            description: 'QA complete workflow seeder executed',
            subject: null,
            properties: ['marker' => 'QA-COMPLETE-WORKFLOW'],
            userId: $this->ctx->admin->id,
        );

        $this->command?->info('Supporting modules (expenses, notifications, activity) ready.');
    }
}
