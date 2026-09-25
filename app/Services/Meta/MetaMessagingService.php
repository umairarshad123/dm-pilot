<?php

namespace App\Services\Meta;

use App\Data\MetaSendResult;
use App\Enums\Platform;
use App\Exceptions\MetaApiException;
use App\Models\MetaAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Outbound Graph API calls for Messenger + Instagram messaging.
 * Never throws from sendText/sendSenderAction; failures come back as a MetaSendResult.
 */
class MetaMessagingService
{
    /** message.metadata markers (Messenger echoes them back) so our own echoes are recognisable. */
    public const METADATA_BOT = 'chatbot:bot';

    public const METADATA_HUMAN = 'chatbot:human';

    /** Messenger profile limits (Messenger Profile API docs). */
    public const GREETING_MAX_CHARS = 160;

    public const ICE_BREAKERS_MAX = 4;

    public const ICE_BREAKER_QUESTION_MAX_CHARS = 80;

    public const ICE_BREAKER_PAYLOAD_MAX_CHARS = 1000;

    public function __construct(
        private readonly MetaGraphClient $client,
        private readonly MessageSplitter $splitter,
    ) {}

    /** Send a text DM. Splits over-long text per platform limit; returns the result of the last chunk. */
    public function sendText(MetaAccount $account, string $recipientId, string $text, ?string $metadata = null): MetaSendResult
    {
        $chunks = $this->chunks($account, $text);

        if ($chunks === []) {
            return new MetaSendResult(ok: false, error: 'Cannot send an empty message.');
        }

        $result = null;

        foreach ($chunks as $i => $chunk) {
            $result = $this->sendChunk($account, $recipientId, $chunk, $metadata);

            if (! $result->ok) {
                // Earlier chunks were already delivered: retrying would duplicate them, so stop here.
                if ($i > 0) {
                    return new MetaSendResult(
                        ok: false,
                        errorCode: $result->errorCode,
                        errorSubcode: $result->errorSubcode,
                        error: sprintf('Partially sent (%d/%d parts). %s', $i, count($chunks), $result->error),
                    );
                }

                return $result;
            }
        }

        return $result;
    }

    /** typing_on | typing_off | mark_seen. Best-effort, never throws. Messenger only. */
    public function sendSenderAction(MetaAccount $account, string $recipientId, string $action): bool
    {
        if ($account->platform !== Platform::Facebook
            || ! in_array($action, ['typing_on', 'typing_off', 'mark_seen'], true)) {
            return false;
        }

        try {
            $this->client->post($account, $this->messagesPath($account), [
                'recipient' => ['id' => $recipientId],
                'sender_action' => $action,
            ]);

            return true;
        } catch (MetaApiException $e) {
            Log::channel('meta')->debug('Sender action failed.', [
                'account_id' => $account->id, 'action' => $action, 'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Validate the account's token against the Graph API.
     *
     * @return array{ok: bool, id?: string, name?: string, expires_at?: ?string, scopes?: array, error?: string}
     */
    public function testConnection(MetaAccount $account): array
    {
        $instagramLogin = $account->auth_type === MetaAccount::AUTH_INSTAGRAM_LOGIN;
        $updates = ['token_checked_at' => Carbon::now()];

        try {
            $me = $this->client->get($account, 'me', [
                'fields' => $instagramLogin ? 'user_id,username,name' : 'id,name',
            ]);

            $result = [
                'ok' => true,
                'id' => (string) ($me['id'] ?? $me['user_id'] ?? ''),
                'name' => (string) ($me['name'] ?? $me['username'] ?? ''),
            ];

            if (! $instagramLogin && ($debug = $this->debugToken($account)) !== null) {
                $expiresAt = (int) ($debug['expires_at'] ?? 0);
                $result['expires_at'] = $expiresAt > 0 ? Carbon::createFromTimestamp($expiresAt, config('app.timezone'))->toIso8601String() : null;
                $result['scopes'] = array_values((array) ($debug['scopes'] ?? []));
                $updates['token_expires_at'] = $expiresAt > 0 ? Carbon::createFromTimestamp($expiresAt, config('app.timezone')) : null;

                if (($debug['is_valid'] ?? true) === false) {
                    $result['ok'] = false;
                    $result['error'] = 'Token is not valid according to debug_token.';
                }
            }
        } catch (MetaApiException $e) {
            $result = ['ok' => false, 'error' => $e->getMessage()];
        }

        $account->forceFill($updates)->save();

        Log::channel('meta')->info('Connection test.', [
            'account_id' => $account->id, 'ok' => $result['ok'], 'error' => $result['error'] ?? null,
        ]);

        return $result;
    }

    /**
     * Subscribe the Page / IG account to this app's webhook messaging fields.
     *
     * @return array{ok: bool, fields?: array, error?: string}
     */
    public function subscribeApp(MetaAccount $account): array
    {
        $instagramLogin = $account->auth_type === MetaAccount::AUTH_INSTAGRAM_LOGIN;
        $fields = (array) config('meta.subscribed_fields.'.($instagramLogin ? 'instagram_login' : 'facebook_login'));

        if (! $instagramLogin && ! $account->page_id) {
            return ['ok' => false, 'error' => 'The account has no Page ID to subscribe.'];
        }

        try {
            $response = $this->client->post(
                $account,
                $instagramLogin ? 'me/subscribed_apps' : $account->page_id.'/subscribed_apps',
                ['subscribed_fields' => implode(',', $fields)],
            );
        } catch (MetaApiException $e) {
            Log::channel('meta')->warning('App subscription failed.', ['account_id' => $account->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $ok = (bool) ($response['success'] ?? false);
        Log::channel('meta')->info('App subscription.', ['account_id' => $account->id, 'ok' => $ok, 'fields' => $fields]);

        return $ok ? ['ok' => true, 'fields' => $fields] : ['ok' => false, 'error' => 'Meta did not confirm the subscription.'];
    }

    /**
     * Read the live Messenger profile (welcome screen) from Meta. Instagram only supports ice breakers.
     *
     * @return array{ok: bool, profile?: array{greeting: ?string, get_started: bool, ice_breakers: list<array{question: string, payload: string}>}, error?: string}
     */
    public function getMessengerProfile(MetaAccount $account): array
    {
        $instagram = $account->platform === Platform::Instagram;
        $query = ['fields' => $instagram ? 'ice_breakers' : 'greeting,get_started,ice_breakers'];

        if ($instagram) {
            $query['platform'] = 'instagram';
        }

        try {
            $response = $this->client->get($account, 'me/messenger_profile', $query);
        } catch (MetaApiException $e) {
            Log::channel('meta')->warning('Messenger profile read failed.', ['account_id' => $account->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $data = (array) ($response['data'][0] ?? []);

        return ['ok' => true, 'profile' => [
            'greeting' => $this->pickLocale((array) ($data['greeting'] ?? []))['text'] ?? null,
            'get_started' => filled($data['get_started']['payload'] ?? null),
            'ice_breakers' => $this->parseIceBreakers((array) ($data['ice_breakers'] ?? [])),
        ]];
    }

    /**
     * Update the Messenger profile: greeting text, Get Started button (payload GET_STARTED) and ice breakers.
     * Only keys present in $config are changed: null/'' greeting, get_started=false or [] ice_breakers REMOVE
     * that property. Instagram supports ice breakers only (greeting / get_started are ignored with a warning).
     * Ice breaker payload defaults to ICE_BREAKER_{n}. On success the config is saved to
     * meta_accounts.settings['messenger_profile']. Never throws.
     *
     * @param  array{greeting?: ?string, get_started?: bool, ice_breakers?: list<array{question: string, payload?: ?string}>}  $config
     * @return array{ok: bool, profile?: array, warnings?: list<string>, error?: string}
     */
    public function setMessengerProfile(MetaAccount $account, array $config): array
    {
        $instagram = $account->platform === Platform::Instagram;
        $set = [];
        $delete = [];
        $saved = [];
        $warnings = [];

        if ($instagram && (array_key_exists('greeting', $config) || array_key_exists('get_started', $config))) {
            $warnings[] = 'Greeting text and the Get Started button are Messenger-only; ignored for Instagram.';
        }

        if (! $instagram && array_key_exists('greeting', $config)) {
            $greeting = trim((string) $config['greeting']);

            if (mb_strlen($greeting) > self::GREETING_MAX_CHARS) {
                return ['ok' => false, 'error' => 'Greeting must be at most '.self::GREETING_MAX_CHARS.' characters.'];
            }

            if ($greeting === '') {
                $delete[] = 'greeting';
            } else {
                $set['greeting'] = [['locale' => 'default', 'text' => $greeting]];
            }

            $saved['greeting'] = $greeting === '' ? null : $greeting;
        }

        if (! $instagram && array_key_exists('get_started', $config)) {
            $enabled = (bool) $config['get_started'];

            if ($enabled) {
                $set['get_started'] = ['payload' => (string) config('bot.automations.get_started_payload', 'GET_STARTED')];
            } else {
                $delete[] = 'get_started';
            }

            $saved['get_started'] = $enabled;
        }

        if (array_key_exists('ice_breakers', $config)) {
            $iceBreakers = $this->normalizeIceBreakers((array) $config['ice_breakers']);

            if (is_string($iceBreakers)) {
                return ['ok' => false, 'error' => $iceBreakers];
            }

            if ($iceBreakers === []) {
                $delete[] = 'ice_breakers';
            } else {
                $set['ice_breakers'] = [['call_to_actions' => $iceBreakers, 'locale' => 'default']];
            }

            $saved['ice_breakers'] = $iceBreakers;
        }

        $merged = array_merge($account->messengerProfile(), $saved);

        if (! $instagram && ($merged['get_started'] ?? false) && ($merged['ice_breakers'] ?? []) !== []) {
            $warnings[] = 'Meta shows ice breakers instead of the Get Started button when both are set.';
        }

        $platform = $instagram ? ['platform' => 'instagram'] : [];
        $context = ['account_id' => $account->id, 'set' => array_keys($set), 'delete' => $delete];

        try {
            if ($set !== []) {
                $this->client->post($account, 'me/messenger_profile', $platform + $set);
            }

            if ($delete !== []) {
                $this->client->delete($account, 'me/messenger_profile', $platform + ['fields' => $delete]);
            }
        } catch (MetaApiException $e) {
            Log::channel('meta')->warning('Messenger profile update failed.', $context + ['error' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $merged['updated_at'] = Carbon::now()->toIso8601String();
        $account->saveMessengerProfile($merged);
        Log::channel('meta')->info('Messenger profile updated.', $context);

        return ['ok' => true, 'profile' => $merged] + ($warnings !== [] ? ['warnings' => $warnings] : []);
    }

    /** @return list<string> */
    public function chunks(MetaAccount $account, string $text): array
    {
        $platform = $account->platform->value;

        return $this->splitter->split(
            $text,
            (int) config("meta.max_message_length.$platform", 1000),
            config("meta.message_length_unit.$platform") === 'bytes',
        );
    }

    private function sendChunk(MetaAccount $account, string $recipientId, string $text, ?string $metadata): MetaSendResult
    {
        $message = ['text' => $text];
        $body = ['recipient' => ['id' => $recipientId]];

        // messaging_type + metadata are Messenger features; the Instagram docs omit them.
        if ($account->platform === Platform::Facebook) {
            $body['messaging_type'] = 'RESPONSE';

            if ($metadata !== null) {
                $message['metadata'] = $metadata;
            }
        }

        $body['message'] = $message;
        $context = ['account_id' => $account->id, 'platform' => $account->platform->value, 'recipient_id' => $recipientId];

        try {
            $response = $this->client->post($account, $this->messagesPath($account), $body);
        } catch (MetaApiException $e) {
            Log::channel('meta')->warning('Message send failed.', $context + [
                'error' => $e->getMessage(),
                'code' => $e->graphCode,
                'subcode' => $e->graphSubcode,
                'http_status' => $e->httpStatus,
                'retryable' => $e->retryable,
                'fbtrace_id' => $e->fbtraceId,
            ]);

            return $e->toSendResult();
        }

        $messageId = isset($response['message_id']) ? (string) $response['message_id'] : null;
        Log::channel('meta')->info('Message sent.', $context + ['message_id' => $messageId, 'length' => mb_strlen($text)]);

        return new MetaSendResult(ok: true, messageId: $messageId);
    }

    /** @return list<array{question: string, payload: string}>|string list, or an error message */
    private function normalizeIceBreakers(array $items): array|string
    {
        $normalized = [];

        foreach (array_values($items) as $i => $item) {
            $question = trim((string) (is_array($item) ? ($item['question'] ?? '') : $item));
            $payload = trim((string) (is_array($item) ? ($item['payload'] ?? '') : ''));

            if ($question === '') {
                continue;
            }

            if (mb_strlen($question) > self::ICE_BREAKER_QUESTION_MAX_CHARS) {
                return 'Each ice breaker question must be at most '.self::ICE_BREAKER_QUESTION_MAX_CHARS.' characters.';
            }

            if (mb_strlen($payload) > self::ICE_BREAKER_PAYLOAD_MAX_CHARS) {
                return 'Ice breaker payload is too long.';
            }

            $normalized[] = ['question' => $question, 'payload' => $payload !== '' ? $payload : 'ICE_BREAKER_'.($i + 1)];
        }

        if (count($normalized) > self::ICE_BREAKERS_MAX) {
            return 'At most '.self::ICE_BREAKERS_MAX.' ice breakers are allowed.';
        }

        return $normalized;
    }

    /** Accepts the locale format [{call_to_actions: [...], locale}] and the legacy [{question, payload}] format. */
    private function parseIceBreakers(array $items): array
    {
        $first = $items[0] ?? null;
        $actions = is_array($first) && isset($first['call_to_actions'])
            ? (array) ($this->pickLocale($items)['call_to_actions'] ?? [])
            : $items;

        $list = [];

        foreach ($actions as $action) {
            if (is_array($action) && filled($action['question'] ?? null)) {
                $list[] = ['question' => (string) $action['question'], 'payload' => (string) ($action['payload'] ?? '')];
            }
        }

        return $list;
    }

    /** The locale=default entry of a localised list, else the first one. */
    private function pickLocale(array $items): array
    {
        foreach ($items as $item) {
            if (is_array($item) && ($item['locale'] ?? null) === 'default') {
                return $item;
            }
        }

        return is_array($items[0] ?? null) ? $items[0] : [];
    }

    private function messagesPath(MetaAccount $account): string
    {
        $id = $account->auth_type === MetaAccount::AUTH_INSTAGRAM_LOGIN ? $account->instagram_account_id : $account->page_id;

        return ($id ?: 'me').'/messages';
    }

    /** @return array<string, mixed>|null debug_token data, or null when the app token is not configured / fails */
    private function debugToken(MetaAccount $account): ?array
    {
        $appId = config('meta.app_id');
        $secret = config('meta.app_secret');

        if (! $appId || ! $secret) {
            return null;
        }

        try {
            $response = $this->client->getWithQuery((string) config('meta.graph_url'), 'debug_token', [
                'input_token' => (string) $account->access_token,
                'access_token' => $appId.'|'.$secret,
            ]);
        } catch (MetaApiException $e) {
            Log::channel('meta')->notice('debug_token failed.', ['account_id' => $account->id, 'error' => $e->getMessage()]);

            return null;
        }

        return is_array($response['data'] ?? null) ? $response['data'] : null;
    }
}
