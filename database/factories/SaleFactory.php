<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Sale> */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'customer_id' => Customer::factory(),
            'customer_name_snapshot' => 'Factory Customer',
            'customer_phone_snapshot' => '0700000099',
            'invoice_number' => 'INV-'.$this->faker->unique()->numerify('######'),
            'receipt_no' => 'RCP-'.$this->faker->unique()->numerify('######'),
            'subtotal_afn' => 4500,
            'discount_afn' => 0,
            'total_afn' => 4500,
            'previous_due_afn' => 0,
            'new_sale_amount_afn' => 4500,
            'grand_total_due_afn' => 4500,
            'received_now_afn' => 4500,
            'remaining_due_afn' => 0,
            'payment_method' => 'cash',
            'paid_afn' => 4500,
            'due_afn' => 0,
            'total_cost_afn' => 3000,
            'total_profit_afn' => 1500,
            'total_investor_profit_afn' => 0,
            'store_profit_afn' => 1500,
            'sale_date' => now()->toDateString(),
            'payment_status' => 'paid',
            'status' => 'completed',
            'printed_count' => 0,
            'trash' => false,
        ];
    }
}
