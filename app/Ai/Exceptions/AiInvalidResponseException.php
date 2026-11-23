<?php

namespace App\Ai\Exceptions;

class AiInvalidResponseException extends AiException
{
    public static function make(string $reason = 'AI provider returned an invalid response.'): self
    {
        return new self($reason);
    }
}
