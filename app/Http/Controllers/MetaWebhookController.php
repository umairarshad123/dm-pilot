<?php

namespace App\Http\Controllers;

use App\Enums\Platform;
use App\Jobs\ProcessMetaWebhookEvent;
use App\Models\WebhookEvent;
use App\Support\PayloadSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MetaWebhookController extends Controller
{
    /** GET: Meta subscription verification handshake. */
    public function verify(Request $request): Response
    {
        // PHP turns `hub.mode` into `hub_mode`; accept both spellings.
        $mode = (string) ($request->query('hub_mode') ?? $request->query('hub.mode') ?? '');
        $token = (string) ($request->query('hub_verify_token') ?? $request->query('hub.verify_token') ?? '');
        $challenge = (string) ($request->query('hub_challenge') ?? $request->query('hub.challenge') ?? '');

        $expected = (string) config('meta.verify_token');

        if ($expected === '') {
            Log::channel('meta')->error('Webhook verification failed: META_VERIFY_TOKEN not configured.', ['ip' => $request->ip()]);

            return response('Forbidden', 403);
        }

        if ($mode === 'subscribe' && $token !== '' && hash_equals($expected, $token)) {
            Log::channel('meta')->info('Webhook verification succeeded.', ['ip' => $request->ip()]);

            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::channel('meta')->warning('Webhook verification failed.', [
            'ip' => $request->ip(),
            'mode' => mb_substr($mode, 0, 30),
            'token_present' => $token !== '',
        ]);

        return response('Forbidden', 403);
    }

    /** POST: store the raw event, queue processing, acknowledge fast. */
    public function receive(Request $request): Response
    {
        $raw = $request->getContent();
        $payload = json_decode($raw, true);

        if (! is_array($payload) || ! isset($payload['object']) || ! is_string($payload['object'])) {
            Log::channel('meta')->warning('Webhook rejected: invalid JSON or missing object.', [
                'ip' => $request->ip(),
                'content_length' => strlen($raw),
            ]);

            return response('Bad Request', 400);
        }

        $object = mb_substr($payload['object'], 0, 30);
        $supported = Platform::fromWebhookObject($object) !== null;
        $hash = hash('sha256', $raw);
        $now = now();

        $inserted = WebhookEvent::query()->insertOrIgnore([
            'object' => $object,
            'payload_hash' => $hash,
            'payload' => json_encode(PayloadSanitizer::sanitize($payload), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'status' => $supported ? 'pending' : 'ignored',
            'messages_count' => 0,
            'processed_at' => $supported ? null : $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $event = WebhookEvent::query()->where('payload_hash', $hash)->first(['id', 'status']);

        if ($inserted === 0) {
            Log::channel('meta')->info('Duplicate webhook delivery ignored.', ['webhook_event_id' => $event?->id, 'object' => $object]);

            return $this->ack();
        }

        $context = [
            'webhook_event_id' => $event?->id,
            'object' => $object,
            'entries' => is_array($payload['entry'] ?? null) ? count($payload['entry']) : 0,
        ];

        if (! $supported) {
            Log::channel('meta')->info('Webhook with unsupported object stored as ignored.', $context);

            return $this->ack();
        }

        Log::channel('meta')->info('Webhook received.', $context);

        ProcessMetaWebhookEvent::dispatch($event->id)->onQueue(config('meta.queue'));

        return $this->ack();
    }

    private function ack(): Response
    {
        return response('EVENT_RECEIVED', 200)->header('Content-Type', 'text/plain');
    }
}
