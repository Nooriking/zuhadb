<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'variant_id' => ProductVariant::factory(),
            'quantity' => 25,
            'reserved_quantity' => 0,
            'available_quantity' => 25,
            'average_cost_afn' => 3000,
            'last_movement_at' => now(),
            'trash' => false,
        ];
    }
}
