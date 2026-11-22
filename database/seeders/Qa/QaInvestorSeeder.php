<?php

namespace Database\Seeders\Qa;

use App\Models\Investment;
use App\Models\Investor;
use App\Models\User;
use App\Services\InvestmentCapitalService;
use Database\Seeders\Qa\Concerns\GuardsQaDatabase;
use Database\Seeders\Qa\Concerns\QaContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Creates investor identities through the User/Investor workflow,
 * then records capital via InvestmentCapitalService (never fabricates wallet balances).
 */
class QaInvestorSeeder extends Seeder
{
    use GuardsQaDatabase;

    public function __construct(private readonly QaContext $ctx) {}

    public function run(): void
    {
        $this->assertQaDatabase();
        Auth::login($this->ctx->admin);

        $capital = app(InvestmentCapitalService::class);

        // King Noori — create active first so capital can deposit, then block.
        $king = Investor::query()->where('username', 'new')->first();
        if (! $king) {
            $king = Investor::factory()->create([
                'name' => 'King Noori',
                'username' => 'new',
                'email' => 'king.noori@zuha.qa',
                'phone' => '0701000001',
                'password' => Hash::make('Investor@123'),
                'status' => 'active',
            ]);
        } else {
            $king->update(['role' => User::ROLE_INVESTOR, 'name' => 'King Noori']);
            $king->getOrCreateWallet();
        }

        if (! Investment::query()->where('investor_id', $king->id)->where('notes', 'like', 'QA-CAPITAL-KING%')->exists()) {
            $capital->deposit([
                'user_id' => $king->id,
                'source_type' => 'cash',
                'capital_amount' => 5000,
                'currency_id' => $this->ctx->usd->id,
                'exchange_rate' => 70,
                'investment_date' => '2026-01-05',
                'notes' => 'QA-CAPITAL-KING-USD-5000',
            ]);
            $capital->deposit([
                'user_id' => $king->id,
                'source_type' => 'cash',
                'capital_amount' => 200000,
                'currency_id' => $this->ctx->afn->id,
                'investment_date' => '2026-01-10',
                'notes' => 'QA-CAPITAL-KING-AFN-200000',
            ]);
        }

        $king->update(['status' => 'blocked']);

        // Mustafa Ansari — active investor
        $mustafa = Investor::query()->where('username', 'AnsariUAE')->first();
        if (! $mustafa) {
            $mustafa = Investor::factory()->mustafaAnsari()->create();
        } else {
            $mustafa->update([
                'role' => User::ROLE_INVESTOR,
                'name' => 'Mustafa Ansari',
                'status' => 'active',
            ]);
            $mustafa->getOrCreateWallet();
        }

        if (! Investment::query()->where('investor_id', $mustafa->id)->where('notes', 'like', 'QA-CAPITAL-ANSARI%')->exists()) {
            $capital->deposit([
                'user_id' => $mustafa->id,
                'source_type' => 'cash',
                'capital_amount' => 8000,
                'currency_id' => $this->ctx->usd->id,
                'exchange_rate' => 70,
                'investment_date' => '2026-01-08',
                'notes' => 'QA-CAPITAL-ANSARI-USD-8000',
            ]);
            $capital->deposit([
                'user_id' => $mustafa->id,
                'source_type' => 'cash',
                'capital_amount' => 15000,
                'currency_id' => $this->ctx->aed->id,
                'exchange_rate' => 19,
                'investment_date' => '2026-01-12',
                'notes' => 'QA-CAPITAL-ANSARI-AED-15000',
            ]);
            $capital->deposit([
                'user_id' => $mustafa->id,
                'source_type' => 'cash',
                'capital_amount' => 350000,
                'currency_id' => $this->ctx->afn->id,
                'investment_date' => '2026-01-15',
                'notes' => 'QA-CAPITAL-ANSARI-AFN-350000',
            ]);
        }

        // Extra active partner for multi-investor product scenarios
        $partner = Investor::query()->where('username', 'qa_partner')->first();
        if (! $partner) {
            $partner = Investor::factory()->create([
                'name' => 'QA Partner Investor',
                'username' => 'qa_partner',
                'email' => 'qa.partner@zuha.test',
                'phone' => '0701000003',
                'password' => Hash::make('Investor@123'),
                'status' => 'active',
            ]);
        } else {
            $partner->getOrCreateWallet();
        }

        if (! Investment::query()->where('investor_id', $partner->id)->where('notes', 'like', 'QA-CAPITAL-PARTNER%')->exists()) {
            $capital->deposit([
                'user_id' => $partner->id,
                'source_type' => 'cash',
                'capital_amount' => 150000,
                'currency_id' => $this->ctx->afn->id,
                'investment_date' => '2026-01-20',
                'notes' => 'QA-CAPITAL-PARTNER-AFN-150000',
            ]);
        }

        $this->ctx->kingNoori = $king->fresh(['wallet', 'investments']);
        $this->ctx->mustafaAnsari = $mustafa->fresh(['wallet', 'investments']);
        $this->ctx->qaPartner = $partner->fresh(['wallet', 'investments']);

        $this->command?->info('Investors + capital deposits via InvestmentCapitalService ready.');
        $this->command?->info("  King Noori (new) status={$this->ctx->kingNoori->status}");
        $this->command?->info("  Mustafa Ansari (AnsariUAE) status={$this->ctx->mustafaAnsari->status}");
    }
}
