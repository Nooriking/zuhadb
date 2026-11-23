<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductBatch>
 */
class ProductBatchFactory extends Factory
{
    protected $model = ProductBatch::class;

    public function definition(): array
    {
        $qty = 4;
        $unitAfn = 2800;
        $ledgerId = Currency::ledger()?->id;

        return [
            'branch_id' => Branch::factory(),
            'variant_id' => ProductVariant::factory(),
            'investment_id' => Investment::factory(),
            'batch_code' => 'B-'.$this->faker->unique()->numerify('########'),
            'purchase_currency_id' => $ledgerId ?? Currency::factory(),
            'purchase_amount_currency' => $unitAfn * $qty,
            'quantity' => $qty,
            'remaining_quantity' => $qty,
            'unit_cost_currency' => $unitAfn,
            'exchange_rate' => 1,
            'unit_cost_afn' => $unitAfn,
            'total_cost_afn' => $unitAfn * $qty,
            'profit_per_unit_afn' => 10,
            'store_profit_per_unit_afn' => 0,
            'sale_price_afn' => $unitAfn + 500,
            'received_date' => now()->toDateString(),
            'purchase_date' => now()->toDateString(),
            'payment_status' => 'paid',
            'status' => 'active',
            'trash' => false,
        ];
    }

    /** Store-owned inventory (no linked investment). */
    public function storeOwned(): static
    {
        return $this->state(fn () => [
            'investment_id' => null,
            'profit_per_unit_afn' => 0,
        ]);
    }

    public function usd(float $unitCost = 20, float $rate = 56000, int $qty = 4): static
    {
        return $this->state(function () use ($unitCost, $rate, $qty) {
            $currencyId = Currency::query()->where('code', 'USD')->value('id')
                ?? Currency::factory()->state(['code' => 'USD', 'symbol' => '$']);

            return [
                'purchase_currency_id' => $currencyId,
                'quantity' => $qty,
                'remaining_quantity' => $qty,
                'unit_cost_currency' => $unitCost,
                'purchase_amount_currency' => $unitCost * $qty,
                'exchange_rate' => $rate,
                'unit_cost_afn' => $unitCost * $rate,
                'total_cost_afn' => $unitCost * $rate * $qty,
            ];
        });
    }

    public function aed(float $unitCost = 50, float $rate = 15250, int $qty = 4): static
    {
        return $this->state(function () use ($unitCost, $rate, $qty) {
            $currencyId = Currency::query()->where('code', 'AED')->value('id')
                ?? Currency::factory()->state(['code' => 'AED', 'symbol' => 'د.إ']);

            return [
                'purchase_currency_id' => $currencyId,
                'quantity' => $qty,
                'remaining_quantity' => $qty,
                'unit_cost_currency' => $unitCost,
                'purchase_amount_currency' => $unitCost * $qty,
                'exchange_rate' => $rate,
                'unit_cost_afn' => $unitCost * $rate,
                'total_cost_afn' => $unitCost * $rate * $qty,
            ];
        });
    }
}
