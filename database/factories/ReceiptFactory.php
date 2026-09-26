<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Receipt;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Receipt> */
class ReceiptFactory extends Factory
{
    protected $model = Receipt::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'sale_id' => Sale::factory(),
            'receipt_number' => 'RCT-'.$this->faker->unique()->numerify('######'),
            'amount_afn' => 4500,
            'remaining_due_afn' => 0,
            'receipt_type' => 'sale_invoice',
            'notes' => 'Factory receipt',
        ];
    }
}
