<?php

namespace App\Ai\Exceptions;

use RuntimeException;

class AiException extends RuntimeException
{
    /**
     * Safe message for logs / user-facing responses (no secrets or payloads).
     */
    public function safeMessage(): string
    {
        return $this->getMessage();
    }
}
