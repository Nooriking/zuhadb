<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupplierPayment> */
class SupplierPaymentFactory extends Factory
{
    protected $model = SupplierPayment::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'receipt_number' => 'SPAY-'.$this->faker->unique()->numerify('######'),
            'amount_afn' => 2000,
            'balance_after' => 5000,
            'payment_date' => now()->toDateString(),
            'exchange_rate' => 70,
            'payment_method' => 'cash',
            'notes' => 'Factory supplier payment',
        ];
    }
}
