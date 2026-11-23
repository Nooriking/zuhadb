<?php

namespace Database\Factories;

use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiConversation>
 */
class AiConversationFactory extends Factory
{
    protected $model = AiConversation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->optional()->sentence(3),
            'status' => AiConversation::STATUS_ACTIVE,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => AiConversation::STATUS_ARCHIVED,
        ]);
    }
}
