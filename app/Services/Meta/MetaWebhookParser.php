<?php

namespace App\Services\Meta;

use App\Data\IncomingMetaMessage;
use App\Enums\Platform;
use App\Support\PayloadSanitizer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns a Meta webhook payload (object=page|instagram) into normalized IncomingMetaMessage DTOs.
 * Only `message` events (including echoes) and `postback` events produce DTOs; everything else is
 * counted and skipped. Never throws on unexpected shapes.
 */
class MetaWebhookParser
{
    private const IGNORED_EVENT_TYPES = [
        'read', 'delivery', 'reaction', 'referral', 'optin', 'account_linking', 'policy_enforcement',
        'pass_thread_control', 'take_thread_control', 'request_thread_control', 'app_roles', 'message_edit',
    ];

    /**
     * @return list<IncomingMetaMessage>
     */
    public function parse(array $payload, ?int $webhookEventId = null): array
    {
        $platform = Platform::fromWebhookObject(is_string($payload['object'] ?? null) ? $payload['object'] : null);

        if ($platform === null) {
            $this->log('debug', 'Webhook payload has unsupported object; nothing parsed.', $webhookEventId, [
                'object' => is_scalar($payload['object'] ?? null) ? (string) $payload['object'] : null,
            ]);

            return [];
        }

        $entries = is_array($payload['entry'] ?? null) ? $payload['entry'] : [];
        $messages = [];
        $skipped = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                $skipped['malformed_entry'] = ($skipped['malformed_entry'] ?? 0) + 1;

                continue;
            }

            $accountId = $this->stringId($entry['id'] ?? null);

            foreach (['standby', 'changes'] as $key) {
                if (isset($entry[$key])) {
                    $skipped[$key] = ($skipped[$key] ?? 0) + (is_array($entry[$key]) ? count($entry[$key]) : 1);
                }
            }

            if (! is_array($entry['messaging'] ?? null)) {
                continue;
            }

            if ($accountId === null) {
                $skipped['entry_without_id'] = ($skipped['entry_without_id'] ?? 0) + count($entry['messaging']);

                continue;
            }

            foreach ($entry['messaging'] as $event) {
                try {
                    $result = $this->parseEvent($platform, $accountId, $event, $webhookEventId);
                } catch (Throwable $e) {
                    $result = 'exception_'.class_basename($e);
                }

                if ($result instanceof IncomingMetaMessage) {
                    $messages[] = $result;
                    $this->log('info', 'Parsed incoming message.', $webhookEventId, [
                        'platform' => $platform->value,
                        'mid' => $result->messageId,
                        'is_echo' => $result->isEcho,
                        'text_length' => $result->text === null ? 0 : mb_strlen($result->text),
                        'attachments' => count($result->attachments),
                    ]);
                } else {
                    $skipped[$result] = ($skipped[$result] ?? 0) + 1;
                }
            }
        }

        if ($skipped !== []) {
            $this->log('debug', 'Skipped non-message webhook events.', $webhookEventId, ['skipped' => $skipped]);
        }

        return $messages;
    }

    /** Returns a DTO, or a string skip reason. */
    private function parseEvent(Platform $platform, string $accountId, mixed $event, ?int $webhookEventId): IncomingMetaMessage|string
    {
        if (! is_array($event)) {
            return 'malformed_event';
        }

        $senderId = $this->stringId(is_array($event['sender'] ?? null) ? ($event['sender']['id'] ?? null) : null);
        $recipientId = $this->stringId(is_array($event['recipient'] ?? null) ? ($event['recipient']['id'] ?? null) : null);
        $timestamp = is_numeric($event['timestamp'] ?? null) ? (int) $event['timestamp'] : null;

        if (isset($event['message'])) {
            if (! is_array($event['message'])) {
                return 'malformed_message';
            }

            return $this->parseMessage($platform, $accountId, $event, $senderId, $recipientId, $timestamp, $webhookEventId);
        }

        if (isset($event['postback'])) {
            if (! is_array($event['postback'])) {
                return 'malformed_postback';
            }

            return $this->parsePostback($platform, $accountId, $event, $senderId, $recipientId, $timestamp, $webhookEventId);
        }

        foreach (self::IGNORED_EVENT_TYPES as $type) {
            if (array_key_exists($type, $event)) {
                return $type;
            }
        }

        return 'unknown_event';
    }

    private function parseMessage(Platform $platform, string $accountId, array $event, ?string $senderId, ?string $recipientId, ?int $timestamp, ?int $webhookEventId): IncomingMetaMessage|string
    {
        $message = $event['message'];

        if (! empty($message['is_deleted'])) {
            return 'deleted';
        }

        if (! empty($message['is_unsupported'])) {
            return 'unsupported';
        }

        $mid = $this->stringId($message['mid'] ?? null);

        if ($mid === null) {
            return 'message_without_mid';
        }

        if ($senderId === null || $recipientId === null) {
            return 'message_without_participants';
        }

        $text = is_scalar($message['text'] ?? null) ? (string) $message['text'] : null;
        $text = $text === '' ? null : $text;
        $attachments = $this->normalizeAttachments($message['attachments'] ?? null);

        if ($text === null && $attachments === []) {
            return 'empty_message';
        }

        $isEcho = ! empty($message['is_echo']);

        return new IncomingMetaMessage(
            platform: $platform,
            accountExternalId: $accountId,
            senderId: $senderId,
            recipientId: $recipientId,
            messageId: $mid,
            text: $text,
            attachments: $attachments,
            timestampMs: $timestamp ?? $this->nowMs(),
            isEcho: $isEcho,
            appId: $isEcho ? $this->stringId($message['app_id'] ?? null) : null,
            webhookEventId: $webhookEventId,
            raw: PayloadSanitizer::sanitize($event),
        );
    }

    private function parsePostback(Platform $platform, string $accountId, array $event, ?string $senderId, ?string $recipientId, ?int $timestamp, ?int $webhookEventId): IncomingMetaMessage|string
    {
        $postback = $event['postback'];

        if ($senderId === null || $recipientId === null) {
            return 'postback_without_participants';
        }

        $title = is_scalar($postback['title'] ?? null) ? trim((string) $postback['title']) : '';
        $payloadValue = is_scalar($postback['payload'] ?? null) ? (string) $postback['payload'] : '';

        // Fall back to the button payload when there is no human-readable title.
        $text = $title !== '' ? $title : ($payloadValue !== '' ? $payloadValue : null);

        if ($text === null) {
            return 'empty_postback';
        }

        $mid = $this->stringId($postback['mid'] ?? null)
            ?? 'postback_'.sha1($senderId.'|'.($timestamp ?? '').'|'.$payloadValue);

        return new IncomingMetaMessage(
            platform: $platform,
            accountExternalId: $accountId,
            senderId: $senderId,
            recipientId: $recipientId,
            messageId: $mid,
            text: $text,
            attachments: [],
            timestampMs: $timestamp ?? $this->nowMs(),
            isEcho: false,
            appId: null,
            webhookEventId: $webhookEventId,
            raw: PayloadSanitizer::sanitize($event),
        );
    }

    /**
     * @return list<array{type: string, url: ?string, payload: array}>
     */
    private function normalizeAttachments(mixed $attachments): array
    {
        if (! is_array($attachments)) {
            return [];
        }

        $normalized = [];

        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $payload = is_array($attachment['payload'] ?? null) ? $attachment['payload'] : [];
            $url = $payload['url'] ?? null;
            $type = $attachment['type'] ?? null;

            $normalized[] = [
                'type' => is_string($type) && $type !== '' ? $type : 'unknown',
                'url' => is_string($url) && $url !== '' ? $url : null,
                'payload' => PayloadSanitizer::sanitize($payload),
            ];
        }

        return $normalized;
    }

    private function stringId(mixed $value): ?string
    {
        if (is_int($value) || (is_string($value) && $value !== '')) {
            return (string) $value;
        }

        return null;
    }

    private function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    private function log(string $level, string $message, ?int $webhookEventId, array $context = []): void
    {
        Log::channel('meta')->{$level}($message, ['webhook_event_id' => $webhookEventId] + $context);
    }
}
