<?php

namespace App\Exceptions;

use RuntimeException;

/** A failed AI provider request. Never contains API keys or prompt text. */
class AiProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = false,
        public readonly ?int $retryAfterSeconds = null,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message);
    }
}
