<?php

namespace Database\Factories;

use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiMessage>
 */
class AiMessageFactory extends Factory
{
    protected $model = AiMessage::class;

    public function definition(): array
    {
        return [
            'conversation_id' => AiConversation::factory(),
            'role' => AiMessage::ROLE_USER,
            'content' => fake()->paragraph(),
            'metadata' => null,
        ];
    }

    public function assistant(): static
    {
        return $this->state(fn () => [
            'role' => AiMessage::ROLE_ASSISTANT,
        ]);
    }

    public function withToolMetadata(): static
    {
        return $this->state(fn () => [
            'role' => AiMessage::ROLE_TOOL,
            'metadata' => [
                'tool_name' => 'example_tool',
                'tool_call_id' => fake()->uuid(),
            ],
        ]);
    }
}
