<?php

namespace App\Ai\Exceptions;

class AiRateLimitException extends AiException
{
    public static function make(?int $retryAfterSeconds = null): self
    {
        // $retryAfterSeconds is retained for callers/logs; user message stays generic.
        return new self('AI requests are temporarily limited. Please wait and try again.');
    }
}
