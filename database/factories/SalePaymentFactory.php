<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SalePayment> */
class SalePaymentFactory extends Factory
{
    protected $model = SalePayment::class;

    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'customer_id' => Customer::factory(),
            'amount_afn' => 4500,
            'remaining_due_afn' => 0,
            'payment_method' => 'cash',
            'reference_number' => 'PAY-'.$this->faker->unique()->numerify('####'),
            'notes' => 'Factory payment',
            'paid_at' => now(),
        ];
    }
}
