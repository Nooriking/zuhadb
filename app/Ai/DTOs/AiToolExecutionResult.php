<?php

namespace App\Ai\DTOs;

final class AiToolExecutionResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $toolCallId,
        public readonly string $toolName,
        public readonly bool $ok,
        public readonly array $payload,
    ) {}

    public function toProviderContent(): string
    {
        return json_encode([
            'ok' => $this->ok,
            'tool' => $this->toolName,
            'data' => $this->payload,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
