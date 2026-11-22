<?php

namespace Database\Seeders\Qa;

use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\SaleService;
use Database\Seeders\Qa\Concerns\GuardsQaDatabase;
use Database\Seeders\Qa\Concerns\QaContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Sales consume real FIFO batches through SaleService::createSale.
 */
class QaSaleWorkflowSeeder extends Seeder
{
    use GuardsQaDatabase;

    public function __construct(private readonly QaContext $ctx) {}

    public function run(): void
    {
        $this->assertQaDatabase();
        Auth::login($this->ctx->admin);

        $sales = app(SaleService::class);
        $admin = $this->ctx->admin;
        $cashier = $this->ctx->cashier;
        $afn = $this->ctx->afn;
        $main = $this->ctx->mainBranch->id;
        $herat = $this->ctx->secondBranch->id;

        $scenarios = [
            // 1. Normal cash sale — store product
            [
                'marker' => 'QA-SALE-CASH-SWY',
                'actor' => $cashier,
                'branch_id' => $main,
                'customer_id' => $this->ctx->cashCustomer->id,
                'payment_method' => 'cash',
                'sale_date' => '2026-03-01',
                'items' => [
                    ['key' => 'swy_absolutely', 'qty' => 1, 'price' => '6500'],
                ],
                'pay_full' => true,
            ],
            // 2. Multi-product invoice
            [
                'marker' => 'QA-SALE-MULTI-PROD',
                'actor' => $admin,
                'branch_id' => $main,
                'customer_id' => $this->ctx->cashCustomer->id,
                'payment_method' => 'cash',
                'sale_date' => '2026-03-02',
                'items' => [
                    ['key' => 'dg_light_blue', 'qty' => 1, 'price' => '5100'],
                    ['key' => 'most_wanted', 'qty' => 1, 'price' => '6000'],
                ],
                'pay_full' => true,
            ],
            // 3. Multiple qty same product + FIFO across CDN batches
            [
                'marker' => 'QA-SALE-FIFO-CDN',
                'actor' => $admin,
                'branch_id' => $main,
                'customer_id' => $this->ctx->cashCustomer->id,
                'payment_method' => 'cash',
                'sale_date' => '2026-03-03',
                'items' => [
                    ['key' => 'cdn_intense', 'qty' => 8, 'price' => '3800'], // crosses batch 1 (6) into batch 2
                ],
                'pay_full' => true,
            ],
            // 4. Investor-owned sale (Mustafa)
            [
                'marker' => 'QA-SALE-INV-ANSARI-AF',
                'actor' => $admin,
                'branch_id' => $main,
                'customer_id' => $this->ctx->cashCustomer->id,
                'payment_method' => 'cash',
                'sale_date' => '2026-03-04',
                'items' => [
                    ['key' => 'al_fareed', 'qty' => 1, 'price' => '10000'],
                ],
                'pay_full' => true,
            ],
            // 5. Mixed ownership in one invoice (store + investor)
            [
                'marker' => 'QA-SALE-MIXED-OWN',
                'actor' => $admin,
                'branch_id' => $main,
                'customer_id' => $this->ctx->partialCustomer->id,
                'payment_method' => 'cash',
                'sale_date' => '2026-03-05',
                'items' => [
                    ['key' => 'sultani', 'qty' => 2, 'price' => '7200'], // FIFO: store batch first then investor
                    ['key' => 'explorer', 'qty' => 1, 'price' => '5800'],
                ],
                'pay_full' => true,
            ],
            // 6. Multi-investor product Kalemat qty spanning both investors' batches
            [
                'marker' => 'QA-SALE-MULTI-INV',
                'actor' => $admin,
                'branch_id' => $main,
                'customer_id' => $this->ctx->cashCustomer->id,
                'payment_method' => 'cash',
                'sale_date' => '2026-03-06',
                'items' => [
                    ['key' => 'kalemat', 'qty' => 10, 'price' => '5800'], // 8 Ansari + 2 Partner
                ],
                'pay_full' => true,
            ],
            // 7. Partial payment
            [
                'marker' => 'QA-SALE-PARTIAL',
                'actor' => $admin,
                'branch_id' => $main,
                'customer_id' => $this->ctx->partialCustomer->id,
                'payment_method' => 'cash',
                'sale_date' => '2026-03-07',
                'items' => [
                    ['key' => 'gentleman', 'qty' => 1, 'price' => '7500'],
                ],
                'pay_amount' => 3000,
            ],
            // 8. Credit sale
            [
                'marker' => 'QA-SALE-CREDIT',
                'actor' => $admin,
                'branch_id' => $main,
                'customer_id' => $this->ctx->creditCustomer->id,
                'payment_method' => 'credit',
                'sale_date' => '2026-03-08',
                'items' => [
                    ['key' => 'hawas_gold', 'qty' => 1, 'price' => '4200'],
                ],
                'pay_amount' => 0,
            ],
            // 9. Discounted sale
            [
                'marker' => 'QA-SALE-DISCOUNT',
                'actor' => $admin,
                'branch_id' => $main,
                'customer_id' => $this->ctx->cashCustomer->id,
                'payment_method' => 'cash',
                'sale_date' => '2026-03-09',
                'discount_afn' => 200,
                'items' => [
                    ['key' => 'eros', 'qty' => 1, 'price' => '6500'],
                ],
                'pay_full' => true,
            ],
            // 10. Second branch sale
            [
                'marker' => 'QA-SALE-HERAT',
                'actor' => $admin,
                'branch_id' => $herat,
                'customer_id' => $this->ctx->cashCustomer->id,
                'payment_method' => 'cash',
                'sale_date' => '2026-03-10',
                'items' => [
                    ['key' => 'fahrenheit', 'qty' => 1, 'price' => '8200'],
                ],
                'pay_full' => true,
            ],
            // 11. King Noori owned stock sale (blocked investor still earns on historical ownership)
            [
                'marker' => 'QA-SALE-KING-EROS',
                'actor' => $admin,
                'branch_id' => $main,
                'customer_id' => $this->ctx->cashCustomer->id,
                'payment_method' => 'cash',
                'sale_date' => '2026-03-11',
                'items' => [
                    ['key' => 'eros', 'qty' => 1, 'price' => '6500'],
                ],
                'pay_full' => true,
            ],
            // 12. Partner Billie sale (for return later)
            [
                'marker' => 'QA-SALE-RETURN-SRC',
                'actor' => $admin,
                'branch_id' => $main,
                'customer_id' => $this->ctx->partialCustomer->id,
                'payment_method' => 'cash',
                'sale_date' => '2026-03-12',
                'items' => [
                    ['key' => 'billie', 'qty' => 2, 'price' => '4800'],
                ],
                'pay_full' => true,
            ],
            // 13. Partial return source — Most Wanted remaining stock
            [
                'marker' => 'QA-SALE-PARTIAL-RET-SRC',
                'actor' => $admin,
                'branch_id' => $main,
                'customer_id' => $this->ctx->cashCustomer->id,
                'payment_method' => 'cash',
                'sale_date' => '2026-03-13',
                'items' => [
                    ['key' => 'most_wanted', 'qty' => 2, 'price' => '6000'],
                ],
                'pay_full' => true,
            ],
        ];

        $created = 0;
        foreach ($scenarios as $s) {
            if (Sale::query()->where('notes', $s['marker'])->exists()) {
                continue;
            }

            $items = [];
            $expectedTotal = 0.0;
            foreach ($s['items'] as $line) {
                $variant = $this->ctx->variants->get($line['key']);
                if (! $variant) {
                    $this->command?->warn("Sale {$s['marker']}: missing variant {$line['key']}");
                    continue 2;
                }
                $items[] = [
                    'variant_id' => $variant->id,
                    'quantity' => $line['qty'],
                    'sale_unit_amount' => $line['price'],
                    'discount_amount' => 0,
                ];
                $expectedTotal += ((float) $line['price']) * $line['qty'];
            }

            $discount = (float) ($s['discount_afn'] ?? 0);
            $net = max(0, $expectedTotal - $discount);

            $payments = [];
            if (($s['pay_full'] ?? false) === true) {
                $payments[] = [
                    'amount' => $net,
                    'currency_id' => $afn->id,
                    'payment_method' => 'cash',
                ];
            } elseif (array_key_exists('pay_amount', $s) && (float) $s['pay_amount'] > 0) {
                $payments[] = [
                    'amount' => $s['pay_amount'],
                    'currency_id' => $afn->id,
                    'payment_method' => 'cash',
                ];
            }

            $payload = [
                'branch_id' => $s['branch_id'],
                'customer_id' => $s['customer_id'],
                'sale_date' => $s['sale_date'],
                'sale_currency_id' => $afn->id,
                'usd_to_afn_rate' => '72',
                'aed_to_afn_rate' => '19.5',
                'payment_method' => $s['payment_method'],
                'discount_afn' => $discount,
                'notes' => $s['marker'],
                'items' => $items,
                'payments' => $payments,
            ];

            try {
                $sales->createSale($payload, $s['actor']);
                $created++;
            } catch (ValidationException $e) {
                $this->command?->error("Sale {$s['marker']} failed: ".json_encode($e->errors()));
                throw $e;
            }
        }

        // 14. Insufficient stock attempt (expected failure — do not persist)
        $zeroVariant = $this->ctx->variants->get('rasasi_blue');
        if ($zeroVariant && ! Sale::query()->where('notes', 'QA-SALE-INSUFFICIENT-ATTEMPT')->exists()) {
            try {
                $sales->createSale([
                    'branch_id' => $main,
                    'customer_id' => $this->ctx->cashCustomer->id,
                    'sale_date' => '2026-03-14',
                    'sale_currency_id' => $afn->id,
                    'payment_method' => 'cash',
                    'notes' => 'QA-SALE-INSUFFICIENT-ATTEMPT',
                    'items' => [[
                        'variant_id' => $zeroVariant->id,
                        'quantity' => 1,
                        'sale_unit_amount' => '3200',
                        'discount_amount' => 0,
                    ]],
                    'payments' => [[
                        'amount' => 3200,
                        'currency_id' => $afn->id,
                        'payment_method' => 'cash',
                    ]],
                ], $admin);
                $this->command?->warn('Expected insufficient-stock failure did not occur.');
            } catch (ValidationException $e) {
                $this->command?->info('Insufficient-stock attempt correctly blocked.');
            }
        }

        // 15. Unauthorized investor sale: enforced at HTTP/policy layer (see PosCoreScenariosTest).
        // Do not call SaleService with an investor actor — it would bypass route middleware and mutate stock.
        $this->command?->info('Unauthorized sale scenario documented as HTTP/policy-only (investor lacks sales.create).');

        // 16. Follow-up customer payment against partial sale (mirrors CustomerController::receivePayment).
        $this->seedFollowUpCustomerPayment();

        // 17. Sale cancellation: app has no stock-reversing cancel service (SaleService only creates
        // completed sales; destroy() soft-trashes). Documented as NOT SUPPORTED — do not fabricate.

        $this->command?->info("Sales created via SaleService: {$created}");
    }

    /**
     * Apply a follow-up payment to QA-SALE-PARTIAL via SaleService::addPayment.
     */
    private function seedFollowUpCustomerPayment(): void
    {
        $sale = Sale::query()->where('notes', 'QA-SALE-PARTIAL')->first();
        if (! $sale) {
            $this->command?->warn('Follow-up payment skipped — QA-SALE-PARTIAL missing.');

            return;
        }

        $existing = SalePayment::query()->where('notes', 'QA-PAY-FOLLOWUP-PARTIAL')->first();
        if ($existing) {
            // Repair a prior buggy seed that created the payment row without updating sale.paid_afn.
            $paidRows = (float) SalePayment::query()->where('sale_id', $sale->id)->sum('amount_afn');
            if (abs($paidRows - (float) $sale->paid_afn) > 0.05) {
                $sale->update([
                    'paid_afn' => $paidRows,
                    'due_afn' => round(max(0, (float) $sale->total_afn - $paidRows), 2),
                    'remaining_due_afn' => round(max(0, (float) $sale->total_afn - $paidRows), 2),
                    'payment_status' => $paidRows + 0.01 >= (float) $sale->total_afn ? 'paid' : 'partial',
                ]);
                $this->command?->info('Repaired QA-SALE-PARTIAL paid_afn to match payment rows.');
            }

            return;
        }

        if ((float) $sale->remaining_due_afn <= 0.01) {
            $this->command?->warn('Follow-up payment skipped — partial sale already paid.');

            return;
        }

        $amount = min(1500.0, round((float) $sale->remaining_due_afn, 2));

        app(SaleService::class)->addPayment($sale, [
            'amount' => $amount,
            'currency_id' => $this->ctx->afn->id,
            'payment_method' => 'cash',
            'notes' => 'QA-PAY-FOLLOWUP-PARTIAL',
        ], $this->ctx->admin);

        $this->command?->info('Follow-up customer payment QA-PAY-FOLLOWUP-PARTIAL recorded via SaleService::addPayment.');
    }
}
