<?php

namespace App\Ai\Exceptions;

class AiConnectionException extends AiException
{
    public static function make(string $reason = 'Unable to connect to the AI provider. Please try again.'): self
    {
        return new self($reason);
    }
}
