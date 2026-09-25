<?php

namespace App\Data;

use App\Enums\Platform;

/**
 * One normalized `entry[].messaging[]` message event from a Meta webhook.
 * Produced by App\Services\Meta\MetaWebhookParser, consumed by App\Jobs\ProcessIncomingMetaMessage.
 *
 * For echoes (is_echo = true: a message SENT BY the page/account), senderId is our page/IG id and
 * recipientId is the customer. customerId() always returns the customer side.
 */
final readonly class IncomingMetaMessage
{
    /**
     * @param  array<int, array{type: string, url: ?string, payload: array}>  $attachments
     * @param  array<string, mixed>  $raw  the single messaging event (no tokens)
     */
    public function __construct(
        public Platform $platform,
        public string $accountExternalId, // webhook entry[].id (Page ID or IG account ID)
        public string $senderId,
        public string $recipientId,
        public string $messageId,         // message.mid
        public ?string $text,
        public array $attachments,
        public int $timestampMs,
        public bool $isEcho = false,
        public ?string $appId = null,     // message.app_id on echoes: set when sent via an app/API
        public ?int $webhookEventId = null,
        public array $raw = [],
    ) {}

    public function customerId(): string
    {
        return $this->isEcho ? $this->recipientId : $this->senderId;
    }

    public function toArray(): array
    {
        return get_object_vars($this) + ['platform' => $this->platform->value];
    }

    public static function fromArray(array $data): self
    {
        $data['platform'] = Platform::from($data['platform']);

        return new self(...$data);
    }
}
