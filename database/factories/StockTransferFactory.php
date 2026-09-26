<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\StockTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockTransfer> */
class StockTransferFactory extends Factory
{
    protected $model = StockTransfer::class;

    public function definition(): array
    {
        return [
            'from_branch_id' => Branch::factory(),
            'to_branch_id' => Branch::factory(),
            'transfer_number' => 'TRF-'.$this->faker->unique()->numerify('######'),
            'transfer_date' => now()->toDateString(),
            'status' => 'draft',
            'notes' => 'Factory transfer',
            'trash' => false,
        ];
    }
}
