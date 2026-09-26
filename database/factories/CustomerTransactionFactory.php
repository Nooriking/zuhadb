<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CustomerTransaction> */
class CustomerTransactionFactory extends Factory
{
    protected $model = CustomerTransaction::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'type' => 'sale',
            'amount_afn' => 4500,
            'balance_after' => 4500,
            'reference_type' => 'sale',
            'notes' => 'Factory customer transaction',
        ];
    }
}
