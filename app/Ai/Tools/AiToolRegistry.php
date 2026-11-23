<?php

namespace App\Ai\Tools;

use App\Ai\Contracts\AiTool;
use InvalidArgumentException;

final class AiToolRegistry
{
    /** @var array<string, AiTool> */
    private array $tools = [];

    public function register(AiTool $tool): void
    {
        $name = $tool->name();

        if ($name === '' || isset($this->tools[$name])) {
            throw new InvalidArgumentException("AI tool [{$name}] is invalid or already registered.");
        }

        $this->tools[$name] = $tool;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): AiTool
    {
        if (! isset($this->tools[$name])) {
            throw new InvalidArgumentException("Unknown AI tool [{$name}].");
        }

        return $this->tools[$name];
    }

    /**
     * @return list<AiTool>
     */
    public function all(): array
    {
        return array_values($this->tools);
    }

    /**
     * OpenAI-compatible tools array for chat completions.
     *
     * @return list<array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}>
     */
    public function toProviderSchemas(): array
    {
        $schemas = [];

        foreach ($this->tools as $tool) {
            $schemas[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'parameters' => $tool->parametersSchema(),
                ],
            ];
        }

        return $schemas;
    }
}
