<?php

namespace Database\Seeders\Qa;

use App\Http\Controllers\SaleReturnController;
use App\Models\InvestorProfitEntry;
use App\Models\InvestorTransaction;
use App\Models\InvestorWithdrawal;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use Database\Seeders\Qa\Concerns\GuardsQaDatabase;
use Database\Seeders\Qa\Concerns\QaContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;

/**
 * Returns use SaleReturnController::finalizeReturn (same path as production completion).
 * Withdrawals update wallets using the controller's accounting steps.
 */
class QaReturnWithdrawalSeeder extends Seeder
{
    use GuardsQaDatabase;

    public function __construct(private readonly QaContext $ctx) {}

    public function run(): void
    {
        $this->assertQaDatabase();
        Auth::login($this->ctx->admin);

        $this->seedPartialReturn();
        $this->seedFullReturn();
        $this->seedProfitWithdrawal();
        $this->seedBlockedInvestorWithdrawalDenied();

        $this->command?->info('Returns + withdrawals ready.');
    }

    private function seedPartialReturn(): void
    {
        $sale = Sale::query()->where('notes', 'QA-SALE-PARTIAL-RET-SRC')->first();
        if (! $sale || SaleReturn::query()->where('reason', 'QA-RETURN-PARTIAL')->exists()) {
            return;
        }

        $item = SaleItem::query()->where('sale_id', $sale->id)->first();
        if (! $item) {
            return;
        }

        $return = SaleReturn::create([
            'sale_id' => $sale->id,
            'branch_id' => $sale->branch_id,
            'customer_id' => $sale->customer_id,
            'return_number' => SaleReturn::generateReturnNumber(),
            'return_date' => '2026-03-15',
            'reason' => 'QA-RETURN-PARTIAL',
            'status' => SaleReturn::STATUS_DRAFT,
            'subtotal_afn' => 0,
            'total_afn' => 0,
            'previous_due_afn' => 0,
            'new_due_afn' => 0,
            'refund_amount_afn' => 0,
            'created_by' => $this->ctx->admin->id,
            'trash' => false,
        ]);

        SaleReturnItem::create([
            'sale_return_id' => $return->id,
            'sale_item_id' => $item->id,
            'variant_id' => $item->variant_id,
            'batch_id' => $item->batch_id,
            'quantity' => 1,
            'unit_price_afn' => (float) $item->sale_price_afn,
            'total_afn' => (float) $item->sale_price_afn,
            'condition' => SaleReturnItem::CONDITION_GOOD,
            'expense_bearer' => 'shop',
            'expense_amount_afn' => 0,
        ]);

        $this->finalize($return);
    }

    private function seedFullReturn(): void
    {
        $sale = Sale::query()->where('notes', 'QA-SALE-RETURN-SRC')->first();
        if (! $sale || SaleReturn::query()->where('reason', 'QA-RETURN-FULL')->exists()) {
            return;
        }

        $return = SaleReturn::create([
            'sale_id' => $sale->id,
            'branch_id' => $sale->branch_id,
            'customer_id' => $sale->customer_id,
            'return_number' => SaleReturn::generateReturnNumber(),
            'return_date' => '2026-03-16',
            'reason' => 'QA-RETURN-FULL',
            'status' => SaleReturn::STATUS_DRAFT,
            'subtotal_afn' => 0,
            'total_afn' => 0,
            'previous_due_afn' => 0,
            'new_due_afn' => 0,
            'refund_amount_afn' => 0,
            'created_by' => $this->ctx->admin->id,
            'trash' => false,
        ]);

        foreach (SaleItem::query()->where('sale_id', $sale->id)->get() as $item) {
            SaleReturnItem::create([
                'sale_return_id' => $return->id,
                'sale_item_id' => $item->id,
                'variant_id' => $item->variant_id,
                'batch_id' => $item->batch_id,
                'quantity' => (int) $item->quantity,
                'unit_price_afn' => (float) $item->sale_price_afn,
                'total_afn' => round((float) $item->sale_price_afn * (int) $item->quantity, 2),
                'condition' => SaleReturnItem::CONDITION_GOOD,
                'expense_bearer' => 'shop',
                'expense_amount_afn' => 0,
            ]);
        }

        $this->finalize($return);
    }

    private function finalize(SaleReturn $return): void
    {
        $controller = app(SaleReturnController::class);
        $method = new ReflectionMethod($controller, 'finalizeReturn');
        $method->setAccessible(true);
        $method->invoke($controller, $return->fresh(['items']));
    }

    private function seedProfitWithdrawal(): void
    {
        $investor = $this->ctx->mustafaAnsari->fresh(['wallet']);
        $pending = (float) InvestorProfitEntry::query()
            ->where('investor_id', $investor->id)
            ->where('status', 'pending')
            ->sum('profit_amount_afn');

        if ($pending < 1) {
            $this->command?->warn('No pending profit for Mustafa — skipping withdrawal.');

            return;
        }

        if (InvestorWithdrawal::query()->where('notes', 'QA-WD-PROFIT-PARTIAL')->exists()) {
            return;
        }

        $amount = min(500.0, round($pending / 2, 2));
        $wallet = $investor->getOrCreateWallet();
        $afn = $this->ctx->afn;

        DB::transaction(function () use ($investor, $wallet, $amount, $afn) {
            $withdrawal = InvestorWithdrawal::create([
                'investor_id' => $investor->id,
                'currency_id' => $afn->id,
                'currency_code' => $afn->code,
                'currency_symbol' => $afn->symbol,
                'exchange_rate' => 1,
                'withdrawal_number' => InvestorWithdrawal::generateWithdrawalNumber(),
                'type' => 'profit',
                'amount_currency' => $amount,
                'amount_afn' => $amount,
                'balance_before_afn' => (float) $wallet->profit_balance_afn,
                'balance_after_afn' => (float) $wallet->profit_balance_afn - $amount,
                'withdrawal_date' => '2026-03-17',
                'status' => 'pending',
                'notes' => 'QA-WD-PROFIT-PARTIAL',
                'created_by' => $this->ctx->admin->id,
            ]);

            $withdrawal->approve($this->ctx->admin->id);

            $wallet->decrement('profit_balance_afn', $amount);
            $wallet->increment('total_withdrawn_afn', $amount);

            InvestorTransaction::record(
                investorId: $investor->id,
                type: 'profit_withdrawn',
                amountAfn: $amount,
                balanceType: 'profit',
                options: [
                    'description' => 'QA profit withdrawal '.$withdrawal->withdrawal_number,
                    'transaction_date' => $withdrawal->withdrawal_date?->toDateString(),
                    'currency_id' => $afn->id,
                    'currency_code' => $afn->code,
                    'currency_symbol' => $afn->symbol,
                    'exchange_rate' => 1,
                    'amount_currency' => $amount,
                ]
            );

            // Mark a slice of pending profits as paid to mirror payable-profit behaviour.
            $remaining = $amount;
            $entries = InvestorProfitEntry::query()
                ->where('investor_id', $investor->id)
                ->where('status', 'pending')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($entries as $entry) {
                if ($remaining <= 0) {
                    break;
                }
                $entryAmount = (float) $entry->profit_amount_afn;
                if ($entryAmount <= $remaining + 0.01) {
                    $entry->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'paid_by' => $this->ctx->admin->id,
                    ]);
                    $remaining = round($remaining - $entryAmount, 2);
                }
            }

            $withdrawal->markAsPaid($this->ctx->admin->id);
        });
    }

    private function seedBlockedInvestorWithdrawalDenied(): void
    {
        if (InvestorWithdrawal::query()->where('notes', 'QA-WD-BLOCKED-DENIED')->exists()) {
            return;
        }

        // Record the blocked-investor restriction as a rejected withdrawal request.
        InvestorWithdrawal::create([
            'investor_id' => $this->ctx->kingNoori->id,
            'currency_id' => $this->ctx->afn->id,
            'currency_code' => 'AFN',
            'currency_symbol' => '؋',
            'exchange_rate' => 1,
            'withdrawal_number' => InvestorWithdrawal::generateWithdrawalNumber(),
            'type' => 'capital',
            'amount_currency' => 1000,
            'amount_afn' => 1000,
            'balance_before_afn' => (float) ($this->ctx->kingNoori->wallet?->capital_balance_afn ?? 0),
            'balance_after_afn' => (float) ($this->ctx->kingNoori->wallet?->capital_balance_afn ?? 0),
            'withdrawal_date' => '2026-03-18',
            'status' => 'rejected',
            'notes' => 'QA-WD-BLOCKED-DENIED — blocked investor cannot withdraw',
            'created_by' => $this->ctx->admin->id,
        ]);
    }
}
