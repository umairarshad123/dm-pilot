<?php

namespace App\Services\Meta;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * "Continue with Facebook": Facebook Login for Business, manual (server-side) code flow.
 *
 *   1. authorizationUrl()  → https://www.facebook.com/{v}/dialog/oauth?client_id&redirect_uri&state&response_type=code
 *                            + config_id (Login for Business configuration) or scope (classic permission list)
 *   2. Meta redirects back to redirectUri() with ?code&state (or ?error...)
 *   3. consumeState() checks state against the session (CSRF), then MetaTokenService exchanges the code
 *      (GET /oauth/access_token with the same redirect_uri), makes it long-lived and lists the Pages.
 *
 * Docs: developers.facebook.com/docs/facebook-login/facebook-login-for-business (config_id replaces scope)
 *       developers.facebook.com/docs/facebook-login/guides/advanced/manual-flow (dialog + code exchange)
 */
class FacebookLoginService
{
    public const STATE_KEY = 'meta_oauth_state';

    /** State older than this is rejected (the user abandoned the dialog). */
    public const STATE_TTL_SECONDS = 900;

    public function __construct(private readonly MetaTokenService $tokens) {}

    public function isConfigured(): bool
    {
        return filled(config('meta.app_id')) && filled(config('meta.app_secret'));
    }

    /** Exact URL to add to "Valid OAuth Redirect URIs" in the Meta dashboard. */
    public function redirectUri(): string
    {
        return route('admin.meta-accounts.oauth.callback');
    }

    public function usesConfigId(): bool
    {
        return filled(config('meta.login.config_id'));
    }

    /** @return list<string> */
    public function scopes(): array
    {
        return array_values(array_filter(array_map('strval', (array) config('meta.login.scopes', []))));
    }

    /** Create a fresh CSRF state, remember it in the session and build the login dialog URL. */
    public function start(Session $session): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Set META_APP_ID and META_APP_SECRET in .env to use Continue with Facebook.');
        }

        $state = Str::random(40);
        $session->put(self::STATE_KEY, ['value' => $state, 'at' => now()->getTimestamp()]);

        return $this->authorizationUrl($state);
    }

    public function authorizationUrl(string $state): string
    {
        $params = [
            'client_id' => (string) config('meta.app_id'),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'response_type' => 'code',
        ];

        if ($this->usesConfigId()) {
            $params['config_id'] = (string) config('meta.login.config_id');
            // Login for Business configurations default to the implicit (token) response; ask for a code instead.
            $params['override_default_response_type'] = 'true';
        } else {
            $params['scope'] = implode(',', $this->scopes());
        }

        return rtrim((string) config('meta.login.dialog_url', 'https://www.facebook.com'), '/')
            .'/'.config('meta.graph_version').'/dialog/oauth?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** One-time check of the returned state (removed from the session whatever the outcome). */
    public function consumeState(Session $session, ?string $state): bool
    {
        $stored = $session->pull(self::STATE_KEY);

        if (! is_array($stored) || ! is_string($stored['value'] ?? null) || ! is_string($state) || $state === '') {
            return false;
        }

        if (now()->getTimestamp() - (int) ($stored['at'] ?? 0) > self::STATE_TTL_SECONDS) {
            return false;
        }

        return hash_equals($stored['value'], $state);
    }

    /**
     * code → short-lived user token → long-lived user token → Facebook user + Pages (with pictures).
     *
     * @return array{user: array{id: string, name: ?string}, pages: list<array>}
     *
     * @throws RuntimeException with a token-free message
     */
    public function completeLogin(string $code): array
    {
        $short = $this->tokens->exchangeCodeForUserToken($code, $this->redirectUri());
        $long = $this->tokens->exchangeForLongLivedUserToken($short['access_token']);
        $user = $this->tokens->me($long['access_token']);

        return ['user' => $user, 'pages' => $this->pages($long['access_token'])];
    }

    /** Pages with pictures; falls back to the plain field list if Meta rejects the richer query. */
    public function pages(string $userToken): array
    {
        try {
            return $this->tokens->listPages($userToken, withPictures: true);
        } catch (RuntimeException) {
            return $this->tokens->listPages($userToken);
        }
    }
}
