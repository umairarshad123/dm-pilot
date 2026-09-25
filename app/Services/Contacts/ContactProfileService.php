<?php

namespace App\Services\Contacts;

use App\Enums\Platform;
use App\Exceptions\MetaApiException;
use App\Models\Conversation;
use App\Services\Meta\MetaGraphClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Meta User Profile API lookups for a contact.
 *
 * Messenger (Page token):  GET /{PSID}?fields=first_name,last_name,profile_pic
 * Instagram (Page / IG token): GET /{IGSID}?fields=name,username,profile_pic
 * (graph.facebook.com for Facebook Login accounts, graph.instagram.com for Instagram Login – MetaGraphClient
 * picks the host.) profile_pic URLs are temporary CDN links; the UI must fall back to initials.
 */
class ContactProfileService
{
    public const MESSENGER_FIELDS = 'first_name,last_name,profile_pic';

    public const INSTAGRAM_FIELDS = 'name,username,profile_pic';

    public function __construct(private readonly MetaGraphClient $graph) {}

    /**
     * Fetch and store the profile. Returns true when at least one profile field was saved.
     * Permanent Graph errors (privacy, permissions) are logged at info and swallowed; retryable ones
     * (rate limits, 5xx, network) are rethrown so the queue can retry.
     *
     * @throws MetaApiException only when $e->retryable
     */
    public function fetch(Conversation $conversation): bool
    {
        $account = $conversation->metaAccount;

        if ($account === null || ! $account->active || blank($conversation->external_user_id)) {
            return false;
        }

        $instagram = $conversation->platform === Platform::Instagram;
        $context = ['conversation_id' => $conversation->id, 'platform' => $conversation->platform?->value];

        try {
            $data = $this->graph->get($account, rawurlencode((string) $conversation->external_user_id), [
                'fields' => $instagram ? self::INSTAGRAM_FIELDS : self::MESSENGER_FIELDS,
            ]);
        } catch (MetaApiException $e) {
            if ($e->retryable) {
                throw $e;
            }

            Log::channel('meta')->info('Contact profile unavailable.', $context + [
                'code' => $e->graphCode, 'subcode' => $e->graphSubcode, 'error' => MetaGraphClient::scrub($e->getMessage()),
            ]);
            $this->markFetched($conversation, ['error' => mb_substr(MetaGraphClient::scrub($e->getMessage()), 0, 200)]);

            return false;
        }

        return $this->apply($conversation, $data);
    }

    /** Store a (possibly partial / unexpected) Graph profile response. Tolerates any shape. */
    public function apply(Conversation $conversation, array $data): bool
    {
        $str = fn (string $key): ?string => isset($data[$key]) && is_scalar($data[$key]) && trim((string) $data[$key]) !== ''
            ? mb_substr(trim((string) $data[$key]), 0, 255)
            : null;

        $first = $str('first_name');
        $last = $str('last_name');
        $name = $str('name');
        $username = $str('username');
        $pic = isset($data['profile_pic']) && is_string($data['profile_pic']) && str_starts_with($data['profile_pic'], 'http')
            ? mb_substr($data['profile_pic'], 0, 2000)
            : null;

        if ($name !== null && $first === null && $last === null) {
            $parts = preg_split('/\s+/u', $name, 2) ?: [];
            $first = $parts[0] ?? null;
            $last = $parts[1] ?? null;
        }

        $fullName = $name ?? (trim(($first ?? '').' '.($last ?? '')) ?: null);
        $changes = array_filter([
            'first_name' => $first,
            'last_name' => $last,
            'username' => $username !== null ? ltrim($username, '@') : null,
            'profile_pic_url' => $pic,
        ], fn ($v) => $v !== null);

        // customer_name is the display name: fill it when empty or when it still holds the last auto value.
        $previousAuto = $conversation->meta['profile']['name'] ?? null;

        if ($fullName !== null && (blank($conversation->customer_name) || $conversation->customer_name === $previousAuto)) {
            $changes['customer_name'] = $fullName;
        }

        $this->markFetched($conversation, ['name' => $fullName ?? $previousAuto], $changes);

        return $changes !== [];
    }

    private function markFetched(Conversation $conversation, array $profileMeta, array $changes = []): void
    {
        $fresh = $conversation->newQuery()->find($conversation->id);

        if ($fresh === null) {
            return;
        }

        $meta = (array) ($fresh->meta ?? []);
        $meta['profile'] = array_filter($profileMeta + ['fetched_at' => Carbon::now()->toIso8601String()], fn ($v) => $v !== null);

        $fresh->forceFill($changes + ['meta' => $meta, 'profile_fetched_at' => Carbon::now()])->save();
        $conversation->setRawAttributes($fresh->getAttributes(), true);
    }
}
