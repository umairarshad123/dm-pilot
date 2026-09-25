<?php

namespace App\Services\Meta;

use App\Enums\Platform;
use App\Models\MetaAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Token exchange / discovery against the Graph API (Facebook Login flow).
 *
 * Tokens are sent in the Authorization header wherever the API allows it. The two endpoints that
 * require secrets in the query string (oauth/access_token, debug_token) are wrapped so that the URL
 * and raw transport exceptions are never logged or rethrown. All errors are RuntimeException with
 * scrubbed, token-free messages.
 */
class MetaTokenService
{
    /**
     * Exchange a short-lived user token for a long-lived (~60 day) one.
     *
     * @return array{access_token: string, expires_at: ?Carbon}
     */
    public function exchangeForLongLivedUserToken(string $shortToken): array
    {
        [$appId, $appSecret] = $this->appCredentials();

        $data = $this->send(
            fn () => $this->http()->get($this->url('oauth/access_token'), [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'fb_exchange_token' => $shortToken,
            ]),
            'token exchange',
            [$shortToken, $appSecret],
        );

        $token = $data['access_token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Meta token exchange returned no access token.');
        }

        return [
            'access_token' => $token,
            'expires_at' => isset($data['expires_in']) ? now()->addSeconds((int) $data['expires_in']) : null,
        ];
    }

    /**
     * Pages the user manages, with Page tokens and linked Instagram professional accounts.
     *
     * With $withPictures, also asks for the Page picture and the IG profile picture (keys `picture_url` and
     * `instagram_business_account.profile_picture_url`); used by the admin connect wizard only.
     *
     * @return list<array{id: string, name: string, access_token: string, picture_url?: ?string, instagram_business_account: ?array{id: string, username: ?string, profile_picture_url?: ?string}}>
     */
    public function listPages(string $userToken, bool $withPictures = false): array
    {
        $pages = [];
        $after = null;

        for ($i = 0; $i < 50; $i++) { // hard stop: 50 pages x 100 = 5000 Pages
            $query = array_filter([
                'fields' => $withPictures
                    ? 'id,name,access_token,picture.type(large){url},instagram_business_account{id,username,profile_picture_url}'
                    : 'id,name,access_token,instagram_business_account{id,username}',
                'limit' => 100,
                'after' => $after,
                'appsecret_proof' => $this->appSecretProof($userToken),
            ]);

            $data = $this->send(
                fn () => $this->http()->withToken($userToken)->get($this->url('me/accounts'), $query),
                'list pages',
                [$userToken],
            );

            foreach ((array) ($data['data'] ?? []) as $page) {
                if (! isset($page['id'], $page['access_token'])) {
                    continue;
                }

                $ig = $page['instagram_business_account'] ?? null;
                $row = [
                    'id' => (string) $page['id'],
                    'name' => (string) ($page['name'] ?? $page['id']),
                    'access_token' => (string) $page['access_token'],
                    'instagram_business_account' => isset($ig['id'])
                        ? ['id' => (string) $ig['id'], 'username' => $ig['username'] ?? null]
                        : null,
                ];

                if ($withPictures) {
                    $row['picture_url'] = self::httpsUrl($page['picture']['data']['url'] ?? null);
                    if ($row['instagram_business_account'] !== null) {
                        $row['instagram_business_account']['profile_picture_url'] = self::httpsUrl($ig['profile_picture_url'] ?? null);
                    }
                }

                $pages[] = $row;
            }

            $after = $data['paging']['cursors']['after'] ?? null;
            if (empty($data['paging']['next']) || ! $after) {
                break;
            }
        }

        return $pages;
    }

    /**
     * Inspect a token with the app access token.
     *
     * @return array{is_valid: bool, expires_at: ?Carbon, data_access_expires_at: ?Carbon, scopes: list<string>, type: ?string, app_id: ?string}
     */
    public function debugToken(string $token): array
    {
        [$appId, $appSecret] = $this->appCredentials();
        $appToken = $appId.'|'.$appSecret;

        $data = $this->send(
            fn () => $this->http()->withToken($appToken)->get($this->url('debug_token'), ['input_token' => $token]),
            'debug token',
            [$token, $appSecret, $appToken],
        )['data'] ?? [];

        $time = fn ($ts) => is_numeric($ts) && (int) $ts > 0 ? Carbon::createFromTimestamp((int) $ts, config('app.timezone')) : null;

        return [
            'is_valid' => (bool) ($data['is_valid'] ?? false),
            'expires_at' => $time($data['expires_at'] ?? null), // 0 = never expires
            'data_access_expires_at' => $time($data['data_access_expires_at'] ?? null),
            'scopes' => array_values(array_map('strval', (array) ($data['scopes'] ?? []))),
            'type' => $data['type'] ?? null,
            'app_id' => isset($data['app_id']) ? (string) $data['app_id'] : null,
        ];
    }

    /**
     * Create/update the meta_accounts rows for a Page from listPages(): one facebook row and, when the
     * Page has a linked Instagram professional account, one instagram row using the same Page token.
     *
     * @param  array{id: string, name: string, access_token: string, instagram_business_account: ?array}  $page
     * @return list<MetaAccount>
     */
    public function upsertAccountsForPage(array $page): array
    {
        return DB::transaction(function () use ($page): array {
            $accounts = [];

            $facebook = MetaAccount::firstOrNew(['platform' => Platform::Facebook, 'page_id' => $page['id']]);
            $facebook->fill([
                'auth_type' => MetaAccount::AUTH_FACEBOOK_LOGIN,
                'page_name' => $page['name'],
                'access_token' => $page['access_token'],
                'token_expires_at' => null, // Page tokens from a long-lived user token do not expire
                'active' => true,
            ])->save();
            $accounts[] = $facebook;

            $ig = $page['instagram_business_account'] ?? null;
            if (! empty($ig['id'])) {
                $instagram = MetaAccount::firstOrNew(['platform' => Platform::Instagram, 'instagram_account_id' => $ig['id']]);
                $instagram->fill([
                    'auth_type' => MetaAccount::AUTH_FACEBOOK_LOGIN,
                    'page_id' => $page['id'],
                    'page_name' => $ig['username'] ?? $page['name'],
                    'access_token' => $page['access_token'],
                    'token_expires_at' => null,
                    'active' => true,
                ])->save();
                $accounts[] = $instagram;
            }

            return $accounts;
        });
    }

    /**
     * Facebook Login (manual flow): exchange the `code` from the login dialog for a user access token.
     * $redirectUri must be byte-identical to the redirect_uri used to open the dialog.
     *
     * @return array{access_token: string, expires_at: ?Carbon}
     */
    public function exchangeCodeForUserToken(string $code, string $redirectUri): array
    {
        [$appId, $appSecret] = $this->appCredentials();

        $data = $this->send(
            fn () => $this->http()->get($this->url('oauth/access_token'), [
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ]),
            'code exchange',
            [$code, $appSecret],
        );

        $token = $data['access_token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Meta code exchange returned no access token.');
        }

        return [
            'access_token' => $token,
            'expires_at' => isset($data['expires_in']) ? now()->addSeconds((int) $data['expires_in']) : null,
        ];
    }

    /**
     * The Facebook user behind a user token: app-scoped user id (what Meta sends to the deauthorize /
     * data-deletion callbacks as `user_id`) and display name.
     *
     * @return array{id: string, name: ?string}
     */
    public function me(string $userToken): array
    {
        $data = $this->send(
            fn () => $this->http()->withToken($userToken)->get($this->url('me'), array_filter([
                'fields' => 'id,name',
                'appsecret_proof' => $this->appSecretProof($userToken),
            ])),
            'user lookup',
            [$userToken],
        );

        if (empty($data['id'])) {
            throw new RuntimeException('Meta user lookup returned no id.');
        }

        return ['id' => (string) $data['id'], 'name' => isset($data['name']) ? (string) $data['name'] : null];
    }

    /**
     * Best-effort extra details for the Pages & Channels cards, using the account's own token. Never throws:
     * each field is null when Meta did not answer. Only for Facebook Login accounts (graph.facebook.com).
     *
     * @return array{picture_url: ?string, username: ?string, subscribed: ?bool, subscribed_fields: list<string>}
     */
    public function accountDetails(MetaAccount $account): array
    {
        $details = ['picture_url' => null, 'username' => null, 'subscribed' => null, 'subscribed_fields' => []];
        $token = (string) $account->access_token;

        if ($account->auth_type !== MetaAccount::AUTH_FACEBOOK_LOGIN || $token === '') {
            return $details;
        }

        $get = function (string $path, array $query) use ($token): ?array {
            try {
                return $this->send(
                    fn () => $this->http()->withToken($token)->get($this->url($path), array_filter($query + [
                        'appsecret_proof' => $this->appSecretProof($token),
                    ])),
                    'account details',
                    [$token],
                );
            } catch (RuntimeException) {
                return null;
            }
        };

        if ($account->platform === Platform::Instagram && $account->instagram_account_id) {
            $ig = $get($account->instagram_account_id, ['fields' => 'username,profile_picture_url']);
            $details['picture_url'] = self::httpsUrl($ig['profile_picture_url'] ?? null);
            $details['username'] = isset($ig['username']) ? (string) $ig['username'] : null;
        } elseif ($account->page_id) {
            $page = $get($account->page_id, ['fields' => 'picture.type(large){url}']);
            $details['picture_url'] = self::httpsUrl($page['picture']['data']['url'] ?? null);
        }

        if ($account->page_id) {
            $apps = $get($account->page_id.'/subscribed_apps', []);
            if ($apps !== null) {
                $appId = (string) config('meta.app_id');
                $mine = collect((array) ($apps['data'] ?? []))->first(fn ($app) => $appId === '' || (string) ($app['id'] ?? '') === $appId);
                $details['subscribed'] = $mine !== null;
                $details['subscribed_fields'] = array_values(array_map('strval', (array) ($mine['subscribed_fields'] ?? [])));
            }
        }

        return $details;
    }

    /** Only keep absolute https URLs (profile pictures are rendered in <img src>). */
    private static function httpsUrl(mixed $url): ?string
    {
        return is_string($url) && str_starts_with($url, 'https://') && strlen($url) <= 2048 ? $url : null;
    }

    public function appSecretProof(string $token): ?string
    {
        $secret = (string) config('meta.app_secret');

        return $secret !== '' ? hash_hmac('sha256', $token, $secret) : null;
    }

    /**
     * Run a request, converting every failure into a scrubbed RuntimeException.
     *
     * @param  list<string>  $secrets  values to redact from any error text
     * @return array<string, mixed>
     */
    private function send(callable $request, string $action, array $secrets): array
    {
        try {
            /** @var Response $response */
            $response = $request();
        } catch (Throwable $e) {
            // Transport exceptions may embed the full request URL (with secrets): never log/rethrow them raw.
            Log::warning("Meta {$action} request failed", ['exception' => $e::class]);

            throw new RuntimeException("Meta {$action} request failed (network error).");
        }

        $json = $response->json();

        if ($response->failed() || ! is_array($json) || isset($json['error'])) {
            $error = is_array($json['error'] ?? null) ? $json['error'] : [];
            $message = $this->scrub((string) ($error['message'] ?? 'HTTP '.$response->status()), $secrets);
            $code = isset($error['code']) ? ' (code '.$error['code'].')' : '';

            Log::warning("Meta {$action} failed", ['status' => $response->status(), 'code' => $error['code'] ?? null, 'type' => $error['type'] ?? null]);

            throw new RuntimeException("Meta {$action} failed: {$message}{$code}");
        }

        return $json;
    }

    /** @param list<string> $secrets */
    private function scrub(string $text, array $secrets): string
    {
        foreach (array_filter($secrets) as $secret) {
            $text = str_replace($secret, '[REDACTED]', $text);
        }

        $text = preg_replace('/((?:access_token|client_secret|fb_exchange_token|input_token|appsecret_proof|code)=)[^&\s"]+/i', '$1[REDACTED]', $text);

        return mb_substr((string) $text, 0, 500);
    }

    /** @return array{0: string, 1: string} */
    private function appCredentials(): array
    {
        $appId = (string) config('meta.app_id');
        $appSecret = (string) config('meta.app_secret');

        if ($appId === '' || $appSecret === '') {
            throw new RuntimeException('META_APP_ID and META_APP_SECRET must be set.');
        }

        return [$appId, $appSecret];
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->timeout((int) config('meta.http_timeout', 15));
    }

    private function url(string $path): string
    {
        return rtrim((string) config('meta.graph_url'), '/').'/'.config('meta.graph_version').'/'.ltrim($path, '/');
    }
}
