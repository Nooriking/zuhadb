<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'expense_number' => 'EXP-'.$this->faker->unique()->numerify('######'),
            'title' => 'Shop rent',
            'category' => 'rent',
            'amount_afn' => 5000,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'description' => 'Demo expense',
            'trash' => false,
        ];
    }
}
