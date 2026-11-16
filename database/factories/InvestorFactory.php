<?php

namespace Database\Factories;

use App\Models\Investor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Investor>
 */
class InvestorFactory extends Factory
{
    protected $model = Investor::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'username' => $this->faker->unique()->userName(),
            'phone' => $this->faker->unique()->numerify('070#######'),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => User::ROLE_INVESTOR,
            'status' => 'active',
            'trash' => false,
        ];
    }

    /**
     * Allow legacy tests to pass full_name.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Investor $investor) {
            if (! empty($investor->getAttributes()['full_name'] ?? null) && empty($investor->name)) {
                $investor->name = $investor->getAttributes()['full_name'];
            }
        });
    }
}
