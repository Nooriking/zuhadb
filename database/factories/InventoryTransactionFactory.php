<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\InventoryTransaction;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryTransaction> */
class InventoryTransactionFactory extends Factory
{
    protected $model = InventoryTransaction::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'variant_id' => ProductVariant::factory(),
            'type' => 'purchase',
            'quantity' => 2,
            'before_quantity' => 0,
            'after_quantity' => 2,
            'notes' => 'Factory stock movement',
        ];
    }
}
