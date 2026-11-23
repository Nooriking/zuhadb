<?php

namespace App\Ai\DTOs;

final class AiCompletionResult
{
    /**
     * @param  list<AiToolCall>  $toolCalls
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $content,
        public readonly string $model,
        public readonly ?string $finishReason = null,
        public readonly array $metadata = [],
        public readonly array $toolCalls = [],
    ) {}

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}
