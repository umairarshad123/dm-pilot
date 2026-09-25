<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Models\MetaAccount;
use App\Services\Meta\FacebookLoginService;
use App\Services\Meta\MetaMessagingService;
use App\Services\Meta\MetaTokenService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * Connect wizard.
 *   Step 1  choose a method: "Continue with Facebook" (OAuth, see FacebookLoginService) or paste a user token
 *   Step 2  pick Pages (nothing pre-selected)
 *   Step 3  save + subscribe webhooks → success screen
 * The discovered Pages (with their Page tokens) live only encrypted in the session between steps and are never
 * passed to a view. Each saved account records the connecting Facebook user's app-scoped id in
 * settings.connected_by_user_id (used by the deauthorize callback).
 */
class MetaConnectController extends Controller
{
    private const SESSION_KEY = 'meta_connect_pages';

    private const RESULT_KEY = 'meta_connect_result';

    public function create(FacebookLoginService $login): View
    {
        return view('admin.meta-accounts.connect', [
            'oauthReady' => $login->isConfigured(),
            'usesConfigId' => $login->usesConfigId(),
            'scopes' => $login->scopes(),
            'redirectUri' => $login->redirectUri(),
        ]);
    }

    /** Paste-a-token method. */
    public function pages(Request $request, MetaTokenService $tokens, FacebookLoginService $login): RedirectResponse
    {
        $token = trim((string) $request->input('user_token'));
        $request->request->remove('user_token'); // never flashed as old input

        if (! preg_match('/^\S{20,2048}$/', $token)) {
            return back()->withErrors(['user_token' => 'Paste a valid user access token.']);
        }

        try {
            $longLived = $tokens->exchangeForLongLivedUserToken($token);
            $pages = $login->pages($longLived['access_token']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        try {
            $user = $tokens->me($longLived['access_token']);
        } catch (RuntimeException) {
            $user = null; // optional: only used for the deauthorize callback
        }

        return $this->toPicker($request, $pages, $user, 'token');
    }

    /** "Continue with Facebook": send the admin to the Facebook Login dialog. */
    public function redirectToFacebook(Request $request, FacebookLoginService $login): RedirectResponse
    {
        try {
            $url = $login->start($request->session());
        } catch (RuntimeException $e) {
            return redirect()->route('admin.meta-accounts.connect')->with('error', $e->getMessage());
        }

        return redirect()->away($url);
    }

    /** OAuth callback (GET): state check, code exchange, then on to the Page picker. */
    public function callback(Request $request, FacebookLoginService $login): RedirectResponse
    {
        $stateOk = $login->consumeState($request->session(), $request->query('state'));

        if (! $stateOk) {
            return redirect()->route('admin.meta-accounts.connect')
                ->with('error', 'The Facebook login could not be verified (expired or invalid state). Please try again.');
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            $reason = (string) $request->query('error_reason', $request->query('error', ''));

            return redirect()->route('admin.meta-accounts.connect')->with('warning', $reason === 'user_denied'
                ? 'Facebook login was cancelled. Nothing was connected.'
                : 'Facebook did not return an authorization code. Please try again.');
        }

        $code = (string) $request->query('code');
        if (strlen($code) > 2048) {
            return redirect()->route('admin.meta-accounts.connect')->with('error', 'Invalid authorization code.');
        }

        try {
            $result = $login->completeLogin($code);
        } catch (RuntimeException $e) {
            Log::warning('Facebook Login callback failed', ['error' => $e->getMessage()]);

            return redirect()->route('admin.meta-accounts.connect')->with('error', $e->getMessage());
        }

        return $this->toPicker($request, $result['pages'], $result['user'], 'facebook_login');
    }

    public function showPages(Request $request): View|RedirectResponse
    {
        $payload = $this->sessionPayload($request);

        if ($payload === null) {
            return redirect()->route('admin.meta-accounts.connect')->with('error', 'Session expired, please connect again.');
        }

        $pageIds = array_column($payload['pages'], 'id');
        $igIds = array_filter(array_map(fn ($p) => $p['instagram_business_account']['id'] ?? null, $payload['pages']));

        $connectedPages = MetaAccount::query()->where('platform', Platform::Facebook)->whereIn('page_id', $pageIds)->pluck('page_id')->all();
        $connectedIg = $igIds ? MetaAccount::query()->where('platform', Platform::Instagram)->whereIn('instagram_account_id', $igIds)->pluck('instagram_account_id')->all() : [];

        // Never pass tokens to the view.
        $pages = array_map(function (array $p) use ($connectedPages, $connectedIg): array {
            $page = array_diff_key($p, ['access_token' => true]);
            $page['connected'] = in_array($p['id'], $connectedPages, true);
            $page['ig_connected'] = isset($p['instagram_business_account']['id']) && in_array($p['instagram_business_account']['id'], $connectedIg, true);

            return $page;
        }, $payload['pages']);

        usort($pages, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return view('admin.meta-accounts.connect-pages', [
            'pages' => $pages,
            'user' => $payload['user'],
            'method' => $payload['method'],
        ]);
    }

    public function store(Request $request, MetaTokenService $tokens, MetaMessagingService $meta): RedirectResponse
    {
        $validated = $request->validate([
            'page_ids' => ['required', 'array', 'min:1'],
            'page_ids.*' => ['string', 'regex:/^\d+$/'],
            'subscribe' => ['nullable', 'boolean'],
        ], ['page_ids.required' => 'Select at least one Page to connect.']);

        $payload = $this->sessionPayload($request);
        if ($payload === null) {
            return redirect()->route('admin.meta-accounts.connect')->with('error', 'Session expired, please connect again.');
        }

        $selected = array_filter($payload['pages'], fn (array $p) => in_array($p['id'], $validated['page_ids'], true));
        $results = [];

        foreach ($selected as $page) {
            foreach ($tokens->upsertAccountsForPage($page) as $account) {
                $picture = $account->platform === Platform::Instagram
                    ? ($page['instagram_business_account']['profile_picture_url'] ?? null)
                    : ($page['picture_url'] ?? null);

                if ($payload['user']['id'] ?? null) {
                    $account->putSetting('connected_by_user_id', (string) $payload['user']['id']);
                }
                $account->putSetting('connected_via', $payload['method']);
                $account->putSetting('connected_at', now()->toIso8601String());
                $account->putSetting('deauthorized_at', null);
                $account->putSetting('token_error', null);
                if ($picture) {
                    $account->putSetting('picture_url', $picture);
                }

                $row = ['id' => $account->id, 'subscribed' => null, 'error' => null];

                if ($request->boolean('subscribe')) {
                    try {
                        $result = $meta->subscribeApp($account);
                    } catch (Throwable $e) {
                        $result = ['ok' => false, 'error' => class_basename($e)];
                    }
                    MetaAccountController::recordSubscription($account, $result);
                    $row['subscribed'] = (bool) $result['ok'];
                    $row['error'] = $result['ok'] ? null : ($result['error'] ?? 'unknown error');
                }

                $results[] = $row;
            }
        }

        $request->session()->forget(self::SESSION_KEY);
        $request->session()->put(self::RESULT_KEY, $results);

        return redirect()->route('admin.meta-accounts.connect.done')
            ->with('success', 'Connected '.count($results).' '.str('channel')->plural(count($results)).'.');
    }

    /** Step 3: success screen with next steps. */
    public function done(Request $request): View|RedirectResponse
    {
        $results = collect((array) $request->session()->get(self::RESULT_KEY, []))->keyBy('id');

        if ($results->isEmpty()) {
            return redirect()->route('admin.meta-accounts.index');
        }

        $accounts = MetaAccount::query()->whereKey($results->keys())->orderBy('platform')->orderBy('page_name')->get();

        return view('admin.meta-accounts.connect-done', ['accounts' => $accounts, 'results' => $results]);
    }

    private function toPicker(Request $request, array $pages, ?array $user, string $method): RedirectResponse
    {
        if ($pages === []) {
            return redirect()->route('admin.meta-accounts.connect')->with('error', $method === 'facebook_login'
                ? 'No Pages were shared. Run "Continue with Facebook" again and select your Pages (and their Instagram accounts) in the Facebook dialog.'
                : 'No Pages found for this token. Make sure it has pages_show_list and you manage at least one Page.');
        }

        $request->session()->put(self::SESSION_KEY, Crypt::encrypt([
            'method' => $method,
            'user' => $user,
            'pages' => $pages,
        ]));

        return redirect()->route('admin.meta-accounts.connect.show-pages');
    }

    /** @return array{method: string, user: ?array{id: string, name: ?string}, pages: list<array>}|null */
    private function sessionPayload(Request $request): ?array
    {
        $encrypted = $request->session()->get(self::SESSION_KEY);

        try {
            $data = is_string($encrypted) ? Crypt::decrypt($encrypted) : null;
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        // Legacy format: a plain list of pages.
        if (array_is_list($data)) {
            return ['method' => 'token', 'user' => null, 'pages' => $data];
        }

        return [
            'method' => (string) ($data['method'] ?? 'token'),
            'user' => is_array($data['user'] ?? null) ? $data['user'] : null,
            'pages' => (array) ($data['pages'] ?? []),
        ];
    }
}
