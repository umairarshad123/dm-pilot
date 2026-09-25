<?php

namespace App\Data;

final readonly class MetaSendResult
{
    public function __construct(
        public bool $ok,
        public ?string $messageId = null,   // Meta message_id of the sent message
        public ?int $errorCode = null,
        public ?int $errorSubcode = null,
        public ?string $error = null,       // safe, token-free error text
        public bool $retryable = false,     // transient (rate limit / 5xx / timeout)
        public ?int $retryAfterSeconds = null,
    ) {}
}
