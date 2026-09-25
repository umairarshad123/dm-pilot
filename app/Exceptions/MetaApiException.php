<?php

namespace App\Exceptions;

use App\Data\MetaSendResult;
use RuntimeException;

/**
 * A failed Graph API call. The message is always token-free and safe to show to admins / store.
 */
class MetaApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $graphCode = null,
        public readonly ?int $graphSubcode = null,
        public readonly ?int $httpStatus = null,
        public readonly bool $retryable = false,
        public readonly ?int $retryAfterSeconds = null,
        public readonly ?string $fbtraceId = null,
    ) {
        parent::__construct($message);
    }

    public static function fromSendResult(MetaSendResult $result): self
    {
        return new self(
            self::friendlyMessage($result->errorCode, $result->errorSubcode, $result->error),
            $result->errorCode,
            $result->errorSubcode,
            retryable: $result->retryable,
            retryAfterSeconds: $result->retryAfterSeconds,
        );
    }

    public function toSendResult(): MetaSendResult
    {
        return new MetaSendResult(
            ok: false,
            errorCode: $this->graphCode,
            errorSubcode: $this->graphSubcode,
            error: $this->getMessage(),
            retryable: $this->retryable,
            retryAfterSeconds: $this->retryAfterSeconds,
        );
    }

    /** Human-friendly explanation for the admin UI. */
    public static function friendlyMessage(?int $code, ?int $subcode, ?string $fallback): string
    {
        $hint = match (true) {
            $code === 10 && $subcode === 2018278, $code === 10 => 'Message could not be sent: outside the 24-hour messaging window.',
            $code === 551 => 'This person is not available to receive messages right now.',
            $code === 190 => 'The access token is invalid or expired. Reconnect the account.',
            $code === 200 => 'The app lacks the required messaging permission for this account.',
            default => null,
        };

        return $hint ?? ($fallback ?: 'Meta API request failed.');
    }
}
