<?php

namespace Database\Factories;

use App\Models\Investor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Investor identity is a users row with role=investor.
 * Wallet / capital must be created through getOrCreateWallet() and InvestmentCapitalService.
 *
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
     * Allow legacy tests to pass full_name. Always initialize an empty wallet.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Investor $investor) {
            if (! empty($investor->getAttributes()['full_name'] ?? null) && empty($investor->name)) {
                $investor->name = $investor->getAttributes()['full_name'];
            }
        })->afterCreating(function (Investor $investor) {
            $investor->getOrCreateWallet();
        });
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function blocked(): static
    {
        return $this->state(fn () => ['status' => 'blocked']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    /** Reference investor: King Noori (username new). */
    public function kingNoori(): static
    {
        return $this->state(fn () => [
            'name' => 'King Noori',
            'username' => 'new',
            'email' => 'king.noori@zuha.qa',
            'phone' => '0701000001',
            'password' => Hash::make('Investor@123'),
            'status' => 'blocked',
        ]);
    }

    /** Reference investor: Mustafa Ansari (username AnsariUAE). */
    public function mustafaAnsari(): static
    {
        return $this->state(fn () => [
            'name' => 'Mustafa Ansari',
            'username' => 'AnsariUAE',
            'email' => 'mustafa.ansari@zuha.qa',
            'phone' => '0701000002',
            'password' => Hash::make('Investor@123'),
            'status' => 'active',
        ]);
    }
}
