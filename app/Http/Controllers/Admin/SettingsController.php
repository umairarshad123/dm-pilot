<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataDeletionRequest;
use App\Models\MetaAccount;
use App\Models\WebhookEvent;
use App\Services\AI\AiProviderFactory;
use App\Services\Meta\FacebookLoginService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

/**
 * Settings hub (read-only: configuration lives in .env). Tabs are server-side (?tab=...);
 * the webhook event log (WebhookEventController) shares the same tab bar.
 */
class SettingsController extends Controller
{
    public const TABS = [
        'connection' => ['label' => 'Connection', 'icon' => 'link'],
        'ai' => ['label' => 'AI providers', 'icon' => 'sparkles'],
        'health' => ['label' => 'System health', 'icon' => 'activity'],
        'privacy' => ['label' => 'Data & privacy', 'icon' => 'shield'],
        'webhooks' => ['label' => 'Webhook events', 'icon' => 'webhook'],
        'app-review' => ['label' => 'App Review', 'icon' => 'check-circle'],
    ];

    public function index(Request $request, AiProviderFactory $ai, FacebookLoginService $login): View
    {
        $tab = (string) $request->query('tab', 'connection');
        if (! array_key_exists($tab, self::TABS) || $tab === 'webhooks') {
            $tab = 'connection';
        }

        $data = match ($tab) {
            'ai' => $this->ai($ai),
            'health' => $this->health($ai),
            'privacy' => $this->privacy(),
            'app-review' => $this->appReview($login),
            default => $this->connection($login),
        };

        return view('admin.settings.index', ['tab' => $tab] + $data);
    }

    /** Tab bar items (link tabs) shared with the webhook event pages. */
    public static function tabs(string $active): array
    {
        $items = [];

        foreach (self::TABS as $key => $tab) {
            $items[] = [
                'label' => $tab['label'],
                'icon' => $tab['icon'],
                'href' => $key === 'webhooks' ? route('admin.webhook-events.index') : route('admin.settings.index', $key === 'connection' ? [] : ['tab' => $key]),
                'active' => $key === $active,
            ];
        }

        return $items;
    }

    private function connection(FacebookLoginService $login): array
    {
        return [
            'callbackUrl' => url('/webhooks/meta'),
            'verifyToken' => (string) config('meta.verify_token'),
            'graphVersion' => (string) config('meta.graph_version'),
            'appId' => (string) config('meta.app_id'),
            'appSecretSet' => filled(config('meta.app_secret')),
            'verifySignature' => (bool) config('meta.verify_signature'),
            'oauthRedirectUri' => $login->redirectUri(),
            'loginConfigId' => (string) config('meta.login.config_id'),
            'loginScopes' => $login->scopes(),
            'subscribedFields' => (array) config('meta.subscribed_fields'),
        ];
    }

    private function ai(AiProviderFactory $ai): array
    {
        try {
            $providers = $ai->available();
        } catch (Throwable) {
            $providers = [];
        }

        return [
            'providers' => $providers,
            'defaultProvider' => AiProviderFactory::defaultProvider(),
            'envKeys' => [
                'openai' => ['OPENAI_API_KEY', 'OPENAI_MODEL'],
                'claude' => ['ANTHROPIC_API_KEY', 'ANTHROPIC_MODEL'],
            ],
        ];
    }

    private function health(AiProviderFactory $ai): array
    {
        $activeAccounts = MetaAccount::active()->count();
        $pending = WebhookEvent::where('status', 'pending')->count();
        $stalePending = WebhookEvent::where('status', 'pending')->where('created_at', '<', now()->subMinutes(10))->count();
        $failed = WebhookEvent::where('status', 'failed')->count();
        $failed24h = WebhookEvent::where('status', 'failed')->where('created_at', '>=', now()->subDay())->count();
        $lastWebhook = WebhookEvent::max('created_at');
        $lastWebhook = $lastWebhook ? Carbon::parse($lastWebhook) : null;

        $failedJobs = $this->tableCount('failed_jobs');
        $queuedJobs = config('queue.default') === 'database' ? $this->tableCount('jobs') : null;
        $queue = (string) config('queue.default');

        $aiKey = AiProviderFactory::defaultProvider();
        try {
            $aiConfigured = $ai->for($aiKey)->isConfigured();
        } catch (Throwable) {
            $aiConfigured = false;
        }

        $checks = [
            ['group' => 'Meta', 'label' => 'App ID', 'ok' => filled(config('meta.app_id')), 'value' => filled(config('meta.app_id')) ? 'Set' : 'Missing', 'hint' => 'META_APP_ID: needed for token exchange and Continue with Facebook.'],
            ['group' => 'Meta', 'label' => 'App secret', 'ok' => filled(config('meta.app_secret')), 'value' => filled(config('meta.app_secret')) ? 'Set' : 'Missing', 'hint' => 'META_APP_SECRET: verifies webhook signatures and signed requests.'],
            ['group' => 'Meta', 'label' => 'Verify token', 'ok' => filled(config('meta.verify_token')), 'value' => filled(config('meta.verify_token')) ? 'Set' : 'Missing', 'hint' => 'META_VERIFY_TOKEN: must match the Verify Token in the Meta dashboard.'],
            ['group' => 'Meta', 'label' => 'Webhook signature check', 'ok' => (bool) config('meta.verify_signature'), 'value' => config('meta.verify_signature') ? 'On' : 'Off', 'hint' => 'META_VERIFY_SIGNATURE should be true in production.'],
            ['group' => 'Meta', 'label' => 'Active channels', 'ok' => $activeAccounts > 0, 'value' => (string) $activeAccounts, 'hint' => 'Connect a Page under Pages & Channels.'],
            ['group' => 'AI', 'label' => 'AI provider key ('.$aiKey.')', 'ok' => $aiConfigured, 'value' => $aiConfigured ? 'Configured' : 'Missing key', 'hint' => $aiKey === 'claude' ? 'ANTHROPIC_API_KEY is needed for AI replies.' : 'OPENAI_API_KEY is needed for AI replies.'],
            ['group' => 'Queue', 'label' => 'Queue connection', 'ok' => $queue !== 'sync', 'value' => $queue, 'hint' => 'Use database or redis and keep `php artisan queue:work` running so webhooks return fast.'],
            ['group' => 'Queue', 'label' => 'Failed jobs', 'ok' => ($failedJobs ?? 0) === 0, 'value' => $failedJobs === null ? 'n/a' : (string) $failedJobs, 'hint' => 'Inspect with `php artisan queue:failed`, retry with `php artisan queue:retry all`.', 'warn' => true],
            ['group' => 'Webhooks', 'label' => 'Last webhook received', 'ok' => $lastWebhook !== null && $lastWebhook->gt(now()->subDays(3)), 'value' => $lastWebhook?->diffForHumans() ?? 'Never', 'hint' => 'If this is old, check the callback URL and that Pages are subscribed.', 'warn' => true],
            ['group' => 'Webhooks', 'label' => 'Pending events', 'ok' => $stalePending === 0, 'value' => (string) $pending, 'hint' => $stalePending > 0 ? "{$stalePending} pending for more than 10 minutes: is the queue worker running?" : 'Events waiting for the queue worker.', 'warn' => true],
            ['group' => 'Webhooks', 'label' => 'Failed events (24h)', 'ok' => $failed24h === 0, 'value' => $failed24h.' / '.$failed.' total', 'hint' => 'Open the webhook event log to see the error.', 'warn' => true],
        ];

        return [
            'checks' => $checks,
            'summary' => [
                'ok' => collect($checks)->where('ok', true)->count(),
                'total' => count($checks),
                'errors' => collect($checks)->filter(fn ($c) => ! $c['ok'] && empty($c['warn']))->count(),
                'warnings' => collect($checks)->filter(fn ($c) => ! $c['ok'] && ! empty($c['warn']))->count(),
            ],
            'queuedJobs' => $queuedJobs,
            'queue' => $queue,
            'env' => app()->environment(),
            'debug' => (bool) config('app.debug'),
            'versions' => ['php' => PHP_VERSION, 'laravel' => app()->version()],
        ];
    }

    private function privacy(): array
    {
        return [
            'retention' => (array) config('legal.retention'),
            'operator' => (string) config('legal.operator_name'),
            'contactEmail' => (string) config('legal.contact_email'),
            'subprocessors' => (array) config('legal.ai_subprocessors'),
            'links' => [
                ['label' => 'Privacy Policy', 'url' => url('/privacy'), 'icon' => 'shield'],
                ['label' => 'Terms of Service', 'url' => url('/terms'), 'icon' => 'book'],
                ['label' => 'Data deletion instructions', 'url' => url('/data-deletion'), 'icon' => 'trash'],
            ],
            'deletionRequests' => Schema::hasTable('data_deletion_requests')
                ? DataDeletionRequest::query()->latest('id')->limit(15)->get()
                : collect(),
        ];
    }

    private function appReview(FacebookLoginService $login): array
    {
        $urls = [
            ['label' => 'Privacy Policy URL', 'where' => 'App settings → Basic', 'value' => url('/privacy')],
            ['label' => 'Terms of Service URL', 'where' => 'App settings → Basic', 'value' => url('/terms')],
            ['label' => 'Data deletion callback URL', 'where' => 'App settings → Basic → User data deletion', 'value' => url('/meta/data-deletion')],
            ['label' => 'Data deletion instructions URL (alternative)', 'where' => 'App settings → Basic', 'value' => url('/data-deletion')],
            ['label' => 'Deauthorize callback URL', 'where' => 'Facebook Login for Business → Settings', 'value' => url('/meta/deauthorize')],
            ['label' => 'Valid OAuth Redirect URI', 'where' => 'Facebook Login for Business → Settings', 'value' => $login->redirectUri()],
            ['label' => 'Webhook callback URL', 'where' => 'Messenger / Instagram → Webhooks', 'value' => url('/webhooks/meta')],
            ['label' => 'Website (Site URL)', 'where' => 'App settings → Basic → Website', 'value' => url('/about')],
            ['label' => 'App domain', 'where' => 'App settings → Basic → App domains', 'value' => (string) parse_url(url('/'), PHP_URL_HOST)],
        ];

        $https = str_starts_with(url('/'), 'https://');
        $ngrok = str_contains(url('/'), 'ngrok');

        $checklist = [
            ['title' => 'Public HTTPS domain', 'done' => $https && ! $ngrok, 'body' => $ngrok ? 'You are on an ngrok domain: its browser interstitial can hide your policy pages from reviewers. Deploy to a real domain before submitting.' : ($https ? 'The app is served over HTTPS.' : 'Serve the app over HTTPS.')],
            ['title' => 'Privacy, Terms and Data deletion pages', 'done' => true, 'body' => 'Built in: /privacy, /terms, /data-deletion. Open them in a private window to check they load without login.'],
            ['title' => 'App secret configured', 'done' => filled(config('meta.app_secret')), 'body' => 'Needed to verify the signed_request of the data deletion and deauthorize callbacks.'],
            ['title' => 'Operator name and contact email', 'done' => ! str_contains((string) config('legal.contact_email'), 'example.com'), 'body' => 'Set APP_OPERATOR_NAME and APP_CONTACT_EMAIL (a monitored mailbox) in .env.'],
            ['title' => 'Business Verification', 'done' => null, 'body' => 'Meta Business Suite → Settings → Business info → Business verification. Required for Advanced Access.'],
            ['title' => 'One successful API call per permission', 'done' => MetaAccount::count() > 0, 'body' => 'Connect a Page and exchange a DM with the bot so every permission shows usage in the dashboard.'],
            ['title' => 'Screencasts + usage descriptions', 'done' => null, 'body' => 'Paste-ready texts are in docs/APP_REVIEW.md section 4, reviewer instructions in section 5.'],
            ['title' => 'Switch the app to Live', 'done' => null, 'body' => 'After approval: App Mode → Live, then reconnect each client Page.'],
        ];

        return [
            'urls' => $urls,
            'checklist' => $checklist,
            'permissions' => [
                ['name' => 'pages_show_list', 'why' => 'List the Pages you can connect'],
                ['name' => 'pages_manage_metadata', 'why' => 'Subscribe Pages to webhooks, welcome screen'],
                ['name' => 'pages_read_engagement', 'why' => 'Page name and customer profile'],
                ['name' => 'pages_messaging', 'why' => 'Receive and send Messenger DMs'],
                ['name' => 'instagram_basic', 'why' => 'Linked Instagram account id and username'],
                ['name' => 'instagram_manage_messages', 'why' => 'Receive and send Instagram DMs'],
                ['name' => 'business_management', 'why' => 'Only if Pages are reachable through a Business portfolio'],
            ],
        ];
    }

    private function tableCount(string $table): ?int
    {
        try {
            return Schema::hasTable($table) ? DB::table($table)->count() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
