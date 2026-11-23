<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Currency;
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
        $ledger = Currency::ledger()
            ?? Currency::query()->where('code', 'AFN')->first();

        $amount = 5000;

        return [
            'branch_id' => Branch::factory(),
            'expense_number' => 'EXP-'.$this->faker->unique()->numerify('######'),
            'title' => 'Shop rent',
            'category' => 'rent',
            'currency_id' => $ledger?->id,
            'currency_code' => $ledger?->code ?? 'AFN',
            'currency_symbol' => $ledger?->symbol,
            'amount_currency' => $amount,
            'exchange_rate' => 1,
            'rate_effective_at' => now(),
            'amount_afn' => $amount,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'description' => 'Demo expense',
            'trash' => false,
        ];
    }
}
