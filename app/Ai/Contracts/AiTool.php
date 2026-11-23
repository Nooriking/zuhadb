<?php

namespace App\Ai\Contracts;

use App\Models\User;

interface AiTool
{
    /**
     * Stable tool name exposed to the model (snake_case).
     */
    public function name(): string;

    /**
     * Human-readable description for the model tool schema.
     */
    public function description(): string;

    /**
     * JSON Schema object for tool arguments (OpenAI function parameters).
     *
     * @return array<string, mixed>
     */
    public function parametersSchema(): array;

    /**
     * Whether the authenticated user may invoke this tool.
     */
    public function authorize(User $user): bool;

    /**
     * Execute the tool with validated arguments.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(User $user, array $arguments): array;
}
