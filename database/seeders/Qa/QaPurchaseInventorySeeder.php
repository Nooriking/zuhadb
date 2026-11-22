<?php

namespace Database\Seeders\Qa;

use App\Models\Investment;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Services\PurchaseService;
use Database\Seeders\Qa\Concerns\GuardsQaDatabase;
use Database\Seeders\Qa\Concerns\QaContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Inventory is created only through PurchaseService::receiveStock.
 * Ownership lives on purchase rows / batches / InvestmentProductAllocation — not catalog products.
 */
class QaPurchaseInventorySeeder extends Seeder
{
    use GuardsQaDatabase;

    public function __construct(private readonly QaContext $ctx) {}

    public function run(): void
    {
        $this->assertQaDatabase();
        Auth::login($this->ctx->admin);

        $purchases = app(PurchaseService::class);
        $actor = $this->ctx->stockKeeper;
        $main = $this->ctx->mainBranch->id;
        $herat = $this->ctx->secondBranch->id;

        $mustafaCash = $this->activeCashInvestment($this->ctx->mustafaAnsari->id);
        $partnerCash = $this->activeCashInvestment($this->ctx->qaPartner->id);
        // King is blocked for login, but historical capital still funds stock.
        $kingCash = $this->activeCashInvestment($this->ctx->kingNoori->id);

        $scenarios = [
            // 1. Store-funded AFN — Stronger With YOU
            [
                'marker' => 'QA-PO-STORE-SWY',
                'variant' => 'swy_absolutely',
                'branch' => $main,
                'qty' => 12,
                'purchase_unit' => '5450',
                'sale' => 6500,
                'currency' => $this->ctx->afn->id,
                'funding' => 'office',
                'investor_id' => 'store',
                'supplier' => $this->ctx->supplierAfn->id,
                'paid_now' => 5450 * 12,
                'date' => '2026-02-01',
                'usd_rate' => '71',
                'aed_rate' => '19.2',
            ],
            // 2. Single investor (Mustafa) — Most Wanted
            [
                'marker' => 'QA-PO-ANSARI-MW',
                'variant' => 'most_wanted',
                'branch' => $main,
                'qty' => 10,
                'purchase_unit' => '3616',
                'sale' => 6000,
                'currency' => $this->ctx->afn->id,
                'funding' => 'investor',
                'investor_id' => $this->ctx->mustafaAnsari->id,
                'investment_id' => $mustafaCash?->id,
                'profit_per_unit' => 800,
                'supplier' => $this->ctx->supplierAfn->id,
                'paid_now' => 3616 * 10,
                'date' => '2026-02-02',
                'usd_rate' => '71',
                'aed_rate' => '19.2',
            ],
            // 3. Investor Al Fareed (Ansari) — high value
            [
                'marker' => 'QA-PO-ANSARI-AF',
                'variant' => 'al_fareed',
                'branch' => $main,
                'qty' => 6,
                'purchase_unit' => '8469',
                'sale' => 10000,
                'currency' => $this->ctx->afn->id,
                'funding' => 'investor',
                'investor_id' => $this->ctx->mustafaAnsari->id,
                'investment_id' => $mustafaCash?->id,
                'profit_per_unit' => 1000,
                'supplier' => $this->ctx->supplierAfn->id,
                'paid_now' => 0, // outstanding supplier balance
                'date' => '2026-02-03',
                'usd_rate' => '71',
                'aed_rate' => '19.2',
            ],
            // 4. Multi-investor same product via separate batches — Kalemat
            [
                'marker' => 'QA-PO-ANSARI-KAL',
                'variant' => 'kalemat',
                'branch' => $main,
                'qty' => 8,
                'purchase_unit' => '4200',
                'sale' => 5800,
                'currency' => $this->ctx->afn->id,
                'funding' => 'investor',
                'investor_id' => $this->ctx->mustafaAnsari->id,
                'investment_id' => $mustafaCash?->id,
                'profit_per_unit' => 500,
                'supplier' => $this->ctx->supplierAfn->id,
                'paid_now' => 4200 * 8,
                'date' => '2026-02-04',
                'usd_rate' => '71',
                'aed_rate' => '19.2',
            ],
            [
                'marker' => 'QA-PO-PARTNER-KAL',
                'variant' => 'kalemat',
                'branch' => $main,
                'qty' => 5,
                'purchase_unit' => '4300', // different cost batch
                'sale' => 5800,
                'currency' => $this->ctx->afn->id,
                'funding' => 'investor',
                'investor_id' => $this->ctx->qaPartner->id,
                'investment_id' => $partnerCash?->id,
                'profit_per_unit' => 450,
                'supplier' => $this->ctx->supplierAfn->id,
                'paid_now' => 4300 * 5,
                'date' => '2026-02-05',
                'usd_rate' => '71',
                'aed_rate' => '19.2',
            ],
            // 5. Mixed store + investor — Sultani
            [
                'marker' => 'QA-PO-STORE-SULT',
                'variant' => 'sultani',
                'branch' => $main,
                'qty' => 4,
                'purchase_unit' => '5100',
                'sale' => 7200,
                'currency' => $this->ctx->afn->id,
                'funding' => 'office',
                'investor_id' => 'store',
                'supplier' => $this->ctx->supplierAfn->id,
                'paid_now' => 5100 * 4,
                'date' => '2026-02-06',
                'usd_rate' => '71',
                'aed_rate' => '19.2',
            ],
            [
                'marker' => 'QA-PO-ANSARI-SULT',
                'variant' => 'sultani',
                'branch' => $main,
                'qty' => 4,
                'purchase_unit' => '5200',
                'sale' => 7200,
                'currency' => $this->ctx->afn->id,
                'funding' => 'investor',
                'investor_id' => $this->ctx->mustafaAnsari->id,
                'investment_id' => $mustafaCash?->id,
                'profit_per_unit' => 600,
                'supplier' => $this->ctx->supplierAfn->id,
                'paid_now' => 5200 * 2, // partial payment
                'date' => '2026-02-07',
                'usd_rate' => '71',
                'aed_rate' => '19.2',
            ],
            // 6. USD purchase — Bleu de Chanel
            [
                'marker' => 'QA-PO-USD-BDC',
                'variant' => 'bleu_chanel',
                'branch' => $main,
                'qty' => 5,
                'purchase_unit' => '110', // USD
                'sale' => 9800,
                'currency' => $this->ctx->usd->id,
                'funding' => 'office',
                'investor_id' => 'store',
                'supplier' => $this->ctx->supplierUsd->id,
                'paid_now' => 110 * 5 * 71, // AFN equivalent approx for paid_now
                'date' => '2026-02-08',
                'usd_rate' => '71',
                'aed_rate' => '19.2',
            ],
            // 7. AED purchase — Hawas (investor Mustafa)
            [
                'marker' => 'QA-PO-AED-HAWAS',
                'variant' => 'hawas_gold',
                'branch' => $main,
                'qty' => 8,
                'purchase_unit' => '145', // AED
                'sale' => 4200,
                'currency' => $this->ctx->aed->id,
                'funding' => 'investor',
                'investor_id' => $this->ctx->mustafaAnsari->id,
                'investment_id' => $mustafaCash?->id,
                'profit_per_unit' => 400,
                'supplier' => $this->ctx->supplierUsd->id,
                'paid_now' => 145 * 8 * 19.2,
                'date' => '2026-02-09',
                'usd_rate' => '71',
                'aed_rate' => '19.2',
            ],
            // 8. King Noori funded (blocked investor, historical capital)
            [
                'marker' => 'QA-PO-KING-EROS',
                'variant' => 'eros',
                'branch' => $main,
                'qty' => 6,
                'purchase_unit' => '4800',
                'sale' => 6500,
                'currency' => $this->ctx->afn->id,
                'funding' => 'investor',
                'investor_id' => $this->ctx->kingNoori->id,
                'investment_id' => $kingCash?->id,
                'profit_per_unit' => 700,
                'supplier' => $this->ctx->supplierAfn->id,
                'paid_now' => 4800 * 6,
                'date' => '2026-02-10',
                'usd_rate' => '71',
                'aed_rate' => '19.2',
            ],
            // 9. Second branch stock — Fahrenheit store
            [
                'marker' => 'QA-PO-HERAT-FAH',
                'variant' => 'fahrenheit',
                'branch' => $herat,
                'qty' => 7,
                'purchase_unit' => '6200',
                'sale' => 8200,
                'currency' => $this->ctx->afn->id,
                'funding' => 'office',
                'investor_id' => 'store',
                'supplier' => $this->ctx->supplierAfn->id,
                'paid_now' => 6200 * 7,
                'date' => '2026-02-11',
                'usd_rate' => '71',
                'aed_rate' => '19.2',
            ],
            // 10. Low stock — Joop (qty 2 vs min)
            [
                'marker' => 'QA-PO-LOW-JOOP',
                'variant' => 'joop_homme',
                'branch' => $main,
                'qty' => 2,
                'purchase_unit' => '3200',
                'sale' => 4500,
                'currency' => $this->ctx->afn->id,
                'funding' => 'office',
                'investor_id' => 'store',
                'supplier' => $this->ctx->supplierAfn->id,
                'paid_now' => 3200 * 2,
                'date' => '2026-02-12',
                'usd_rate' => '71',
                'aed_rate' => '19.2',
            ],
            // 11. Critical stock — Kenzo qty 1
            [
                'marker' => 'QA-PO-CRIT-KENZO',
                'variant' => 'kenzo_homme',
                'branch' => $main,
                'qty' => 1,
                'purchase_unit' => '3800',
                'sale' => 5200,
                'currency' => $this->ctx->afn->id,
                'funding' => 'office',
                'investor_id' => 'store',
                'supplier' => $this->ctx->supplierAfn->id,
                'paid_now' => 3800,
                'date' => '2026-02-13',
                'usd_rate' => '71',
                'aed_rate' => '19.2',
            ],
            // 12. FIFO multi-batch same product different costs — CDN Intense
            [
                'marker' => 'QA-PO-CDN-B1',
                'variant' => 'cdn_intense',
                'branch' => $main,
                'qty' => 6,
                'purchase_unit' => '2500',
                'sale' => 3800,
                'currency' => $this->ctx->afn->id,
                'funding' => 'office',
                'investor_id' => 'store',
                'supplier' => $this->ctx->supplierAfn->id,
                'paid_now' => 2500 * 6,
                'date' => '2026-02-01',
                'usd_rate' => '70',
                'aed_rate' => '19',
            ],
            [
                'marker' => 'QA-PO-CDN-B2',
                'variant' => 'cdn_intense',
                'branch' => $main,
                'qty' => 6,
                'purchase_unit' => '2650',
                'sale' => 3800,
                'currency' => $this->ctx->afn->id,
                'funding' => 'office',
                'investor_id' => 'store',
                'supplier' => $this->ctx->supplierAfn->id,
                'paid_now' => 2650 * 6,
                'date' => '2026-02-15',
                'usd_rate' => '71',
                'aed_rate' => '19.2',
            ],
            // 13. More investor products for sales variety
            [
                'marker' => 'QA-PO-ANSARI-GENT',
                'variant' => 'gentleman',
                'branch' => $main,
                'qty' => 5,
                'purchase_unit' => '5500',
                'sale' => 7500,
                'currency' => $this->ctx->afn->id,
                'funding' => 'investor',
                'investor_id' => $this->ctx->mustafaAnsari->id,
                'investment_id' => $mustafaCash?->id,
                'profit_per_unit' => 700,
                'supplier' => $this->ctx->supplierAfn->id,
                'paid_now' => 5500 * 5,
                'date' => '2026-02-16',
                'usd_rate' => '71',
                'aed_rate' => '19.2',
            ],
            [
                'marker' => 'QA-PO-STORE-DG',
                'variant' => 'dg_light_blue',
                'branch' => $main,
                'qty' => 10,
                'purchase_unit' => '3600',
                'sale' => 5100,
                'currency' => $this->ctx->afn->id,
                'funding' => 'office',
                'investor_id' => 'store',
                'supplier' => $this->ctx->supplierAfn->id,
                'paid_now' => 3600 * 10,
                'date' => '2026-02-17',
                'usd_rate' => '71',
                'aed_rate' => '19.2',
            ],
            [
                'marker' => 'QA-PO-ANSARI-EXPL',
                'variant' => 'explorer',
                'branch' => $main,
                'qty' => 5,
                'purchase_unit' => '4100',
                'sale' => 5800,
                'currency' => $this->ctx->afn->id,
                'funding' => 'investor',
                'investor_id' => $this->ctx->mustafaAnsari->id,
                'investment_id' => $mustafaCash?->id,
                'profit_per_unit' => 550,
                'supplier' => $this->ctx->supplierAfn->id,
                'paid_now' => 4100 * 5,
                'date' => '2026-02-18',
                'usd_rate' => '71',
                'aed_rate' => '19.2',
            ],
            [
                'marker' => 'QA-PO-PARTNER-BILLIE',
                'variant' => 'billie',
                'branch' => $main,
                'qty' => 4,
                'purchase_unit' => '3300',
                'sale' => 4800,
                'currency' => $this->ctx->afn->id,
                'funding' => 'investor',
                'investor_id' => $this->ctx->qaPartner->id,
                'investment_id' => $partnerCash?->id,
                'profit_per_unit' => 400,
                'supplier' => $this->ctx->supplierAfn->id,
                'paid_now' => 3300 * 4,
                'date' => '2026-02-19',
                'usd_rate' => '71',
                'aed_rate' => '19.2',
            ],
            // rasasi_blue intentionally NOT purchased → zero stock
        ];

        $created = 0;
        foreach ($scenarios as $s) {
            if (Product::query()->where('notes', $s['marker'])->whereNotNull('variant_id')->exists()) {
                continue;
            }

            $variant = $this->ctx->variants->get($s['variant']);
            if (! $variant) {
                $this->command?->warn("Missing variant key {$s['variant']}");
                continue;
            }

            $payload = [
                'variant_id' => $variant->id,
                'quantity' => $s['qty'],
                'purchase_currency_id' => $s['currency'],
                'purchase_unit_amount' => $s['purchase_unit'],
                'sale_price_afn' => $s['sale'],
                'funding_source' => $s['funding'],
                'investor_id' => $s['investor_id'],
                'supplier_id' => $s['supplier'],
                'invoice_number' => $s['marker'],
                'purchase_date' => $s['date'],
                'paid_now' => $s['paid_now'],
                'usd_to_afn_rate' => $s['usd_rate'],
                'aed_to_afn_rate' => $s['aed_rate'],
                'rate_source' => 'qa-seed',
                'notes' => $s['marker'],
            ];

            if (! empty($s['investment_id'])) {
                $payload['investment_id'] = $s['investment_id'];
            }
            if (! empty($s['profit_per_unit'])) {
                $payload['profit_per_unit'] = $s['profit_per_unit'];
                $payload['profit_currency_id'] = $this->ctx->afn->id;
            }

            $purchases->receiveStock($payload, $actor, $s['branch']);
            $created++;
        }

        $batches = ProductBatch::query()->count();
        $this->command?->info("Purchases received via PurchaseService: {$created} new; batches total={$batches}");
    }

    private function activeCashInvestment(int $investorId): ?Investment
    {
        return Investment::query()
            ->where('investor_id', $investorId)
            ->where('source_type', 'cash')
            ->whereIn('status', ['active', 'partially_used'])
            ->where('trash', false)
            ->where('available_amount_afn', '>', 0)
            ->orderByDesc('id')
            ->first();
    }
}
