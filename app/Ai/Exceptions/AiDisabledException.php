<?php

namespace App\Ai\Exceptions;

class AiDisabledException extends AiException
{
    public static function make(): self
    {
        return new self('AI assistant is disabled.');
    }
}
