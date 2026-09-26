<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\SupplierInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupplierInvoice> */
class SupplierInvoiceFactory extends Factory
{
    protected $model = SupplierInvoice::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'invoice_no' => 'BILL-'.$this->faker->unique()->numerify('######'),
            'purchase_date' => now()->toDateString(),
            'exchange_rate' => 70,
            'total_afn' => 7000,
            'paid_afn' => 0,
            'due_afn' => 7000,
            'notes' => 'Factory supplier bill',
        ];
    }
}
