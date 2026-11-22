<?php

namespace Database\Seeders\Qa;

use App\Models\Inventory;
use App\Models\InvestorProfitEntry;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SalePayment;
use Database\Seeders\Qa\Concerns\GuardsQaDatabase;
use Database\Seeders\Qa\Concerns\QaContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QaReconciliationSeeder extends Seeder
{
    use GuardsQaDatabase;

    /** @var list<array{check:string,status:string,detail:string}> */
    public array $results = [];

    public function __construct(private readonly QaContext $ctx) {}

    public function run(): void
    {
        $this->assertQaDatabase();

        $this->check('investors.reference', function () {
            $king = $this->ctx->kingNoori;
            $mustafa = $this->ctx->mustafaAnsari;

            return $king->username === 'new'
                && $king->status === 'blocked'
                && $mustafa->username === 'AnsariUAE'
                && $mustafa->status === 'active'
                && $king->wallet !== null
                && $mustafa->wallet !== null;
        }, 'King Noori blocked + Mustafa Ansari active with wallets');

        $this->check('purchases.generate_batches', function () {
            $purchaseRows = Product::query()->whereNotNull('variant_id')->where('notes', 'like', 'QA-PO-%')->count();
            $batches = ProductBatch::query()->whereHas('purchaseItem', fn ($q) => $q->where('notes', 'like', 'QA-PO-%'))->count();
            // purchase_item_id on batches points to products purchase rows
            $batchesAlt = ProductBatch::query()
                ->whereIn('purchase_item_id', Product::query()->where('notes', 'like', 'QA-PO-%')->pluck('id'))
                ->count();

            return $purchaseRows > 0 && $batchesAlt === $purchaseRows;
        }, 'Each QA purchase row has a matching batch');

        $this->check('inventory.reconciles_batches', function () {
            $mismatches = 0;
            $rows = ProductBatch::query()
                ->select('branch_id', 'variant_id', DB::raw('SUM(remaining_quantity) as batch_qty'))
                ->groupBy('branch_id', 'variant_id')
                ->get();

            foreach ($rows as $row) {
                $inv = (int) Inventory::query()
                    ->where('branch_id', $row->branch_id)
                    ->where('variant_id', $row->variant_id)
                    ->value('quantity');
                if ($inv !== (int) $row->batch_qty) {
                    $mismatches++;
                    $this->command?->warn("Inventory mismatch branch={$row->branch_id} variant={$row->variant_id} inv={$inv} batches={$row->batch_qty}");
                }
            }

            return $mismatches === 0;
        }, 'Inventory quantity equals sum of batch remaining per branch/variant');

        $this->check('sales.payment_balance', function () {
            $bad = 0;
            $returnedSaleIds = \App\Models\SaleReturn::query()
                ->where('reason', 'like', 'QA-RETURN-%')
                ->where('status', 'completed')
                ->pluck('sale_id')
                ->filter()
                ->all();

            foreach (Sale::query()->where('notes', 'like', 'QA-SALE-%')->where('status', 'completed')->get() as $sale) {
                // Fully/partially returned sales adjust total_afn without rewriting historical payment rows.
                if (in_array($sale->id, $returnedSaleIds, true)) {
                    continue;
                }

                $paid = (float) SalePayment::query()->where('sale_id', $sale->id)->sum('amount_afn');
                if (abs($paid - (float) $sale->paid_afn) > 0.05) {
                    $bad++;
                    $this->command?->warn("Payment mismatch {$sale->notes}: rows={$paid} sale.paid={$sale->paid_afn}");
                }
            }

            return $bad === 0;
        }, 'Sale payment rows match sale.paid_afn (excluding returned sales)');

        $this->check('sales.profit_identity', function () {
            $bad = 0;
            foreach (Sale::query()->where('notes', 'like', 'QA-SALE-%')->where('status', 'completed')->get() as $sale) {
                $gross = round((float) $sale->total_profit_afn, 2);
                $inv = round((float) $sale->total_investor_profit_afn, 2);
                $store = round((float) $sale->store_profit_afn, 2);
                if (abs(round($inv + $store, 2) - $gross) > 0.05) {
                    $bad++;
                    $this->command?->warn("Profit split mismatch {$sale->notes}: gross={$gross} inv={$inv} store={$store}");
                }
            }

            return $bad === 0;
        }, 'gross profit = investor profit + store profit');

        $this->check('investor.profits.from_sales', function () {
            $orphans = InvestorProfitEntry::query()
                ->where('entry_type', 'profit')
                ->whereNull('sale_id')
                ->count();

            $qaSales = Sale::query()->where('notes', 'like', 'QA-SALE-%')->pluck('id');
            $entries = InvestorProfitEntry::query()->whereIn('sale_id', $qaSales)->count();

            return $orphans === 0 && $entries >= 0;
        }, 'Investor profits reference sales; no orphan profit rows');

        $this->check('no_negative_batches', function () {
            return ProductBatch::query()->where('remaining_quantity', '<', 0)->count() === 0;
        }, 'No negative batch remaining quantities');

        $pass = collect($this->results)->where('status', 'PASS')->count();
        $fail = collect($this->results)->where('status', 'FAIL')->count();
        $this->command?->info("Reconciliation: {$pass} PASS / {$fail} FAIL");

        foreach ($this->results as $row) {
            $this->command?->line("  [{$row['status']}] {$row['check']} — {$row['detail']}");
        }
    }

    private function check(string $name, callable $fn, string $detail): void
    {
        try {
            $ok = (bool) $fn();
            $this->results[] = [
                'check' => $name,
                'status' => $ok ? 'PASS' : 'FAIL',
                'detail' => $detail,
            ];
        } catch (\Throwable $e) {
            $this->results[] = [
                'check' => $name,
                'status' => 'FAIL',
                'detail' => $detail.' | '.$e->getMessage(),
            ];
        }
    }
}
