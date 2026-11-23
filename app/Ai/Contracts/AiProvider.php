<?php

namespace App\Ai\Contracts;

use App\Ai\DTOs\AiCompletionResult;
use App\Ai\Exceptions\AiException;

interface AiProvider
{
    /**
     * Send a chat completion request to the configured provider.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  array{
     *     temperature?: float,
     *     max_tokens?: int,
     *     model?: string,
     *     tools?: list<array<string, mixed>>,
     *     tool_choice?: string|array<string, mixed>,
     *     parallel_tool_calls?: bool
     * }  $options
     *
     * @throws AiException
     */
    public function chat(array $messages, array $options = []): AiCompletionResult;

    /**
     * Provider driver name (e.g. openai).
     */
    public function name(): string;
}
