<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SaleReturn> */
class SaleReturnFactory extends Factory
{
    protected $model = SaleReturn::class;

    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'branch_id' => Branch::factory(),
            'customer_id' => Customer::factory(),
            'return_number' => 'RET-'.$this->faker->unique()->numerify('######'),
            'subtotal_afn' => 4500,
            'total_afn' => 4500,
            'previous_due_afn' => 0,
            'new_due_afn' => 0,
            'refund_amount_afn' => 4500,
            'refund_method' => 'cash',
            'reason' => 'Factory return',
            'return_date' => now()->toDateString(),
            'status' => 'completed',
            'trash' => false,
        ];
    }
}
