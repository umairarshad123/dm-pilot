<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Http\Controllers\Controller;
use App\Models\BotSetting;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetaAccount;
use App\Models\WebhookEvent;
use App\Services\AI\AiProviderFactory;
use App\Services\Insights\InsightsService;
use App\Support\CurrentPage;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Throwable;

/**
 * Dashboard (GET /admin) + its JSON feed (GET /admin/dashboard/data, polled every 60s by the page).
 * Analytics come from InsightsService, scoped to the page switcher ($currentPage) and ?period=7|14|30|90.
 */
class DashboardController extends Controller
{
    public const PERIODS = [7, 14, 30, 90];

    public const DEFAULT_PERIOD = 14;

    public function __construct(
        private readonly InsightsService $insights,
        private readonly CurrentPage $currentPage,
        private readonly AiProviderFactory $aiProviders,
    ) {}

    public function index(Request $request): View
    {
        $days = $this->period($request);
        $dash = $this->analytics($days);
        $liveHtml = view('admin.dashboard.partials.live', [...$dash, 'links' => $this->links()])->render();

        $stats = $this->stats();

        return view('admin.dashboard.index', [
            ...$dash,
            'liveHtml' => $liveHtml,
            'liveHash' => md5($liveHtml),
            'stats' => $stats,
            'health' => $this->health($stats),
            'aiProviders' => $this->aiProviderStatus(),
            'checklist' => $this->checklist(),
            'callbackUrl' => url('/webhooks/meta'),
            'verifyToken' => (string) config('meta.verify_token'),
            'graphVersion' => (string) config('meta.graph_version'),
            'links' => $this->links(),
        ]);
    }

    /** JSON for the auto-refresh (and any API consumer): raw analytics + the re-rendered live region. */
    public function data(Request $request): JsonResponse
    {
        $days = $this->period($request);
        $dash = $this->analytics($days);
        $html = view('admin.dashboard.partials.live', [...$dash, 'links' => $this->links()])->render();

        return response()->json([
            'period' => $days,
            'page' => ['id' => $this->currentPage->id(), 'label' => $this->currentPage->label()],
            'overview' => $dash['insights']['overview'],
            'kpis' => collect($dash['kpis'])->map(fn (array $k) => collect($k)->only(['key', 'label', 'value', 'display', 'delta'])->all())->values(),
            'daily' => $dash['insights']['daily'],
            'by_channel' => $dash['insights']['by_channel'],
            'by_page' => $dash['leaderboard'],
            'heatmap' => $dash['insights']['heatmap'],
            'recent' => collect($dash['insights']['recent'])->map(fn (array $r) => [...$r, 'at' => $r['at']?->toIso8601String()])->values(),
            'stats' => $this->stats(),
            'html' => $html,
            'hash' => md5($html),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    // ---------------------------------------------------------------------------------------------

    private function period(Request $request): int
    {
        $days = (int) $request->query('period', (string) self::DEFAULT_PERIOD);

        return in_array($days, self::PERIODS, true) ? $days : self::DEFAULT_PERIOD;
    }

    /** @return array<string, mixed> everything the live region renders */
    private function analytics(int $days): array
    {
        $pageId = $this->currentPage->id();
        $insights = $this->insights->dashboard($pageId, $days, 12);
        [$from, $to] = $this->insights->range($days);
        $extra = $this->extraDailySeries($pageId, $from, $to);
        $daily = $insights['daily'];

        // Leaderboard always lists every page (so it doubles as a switcher); the selected one is highlighted.
        $leaderboard = $pageId === null ? $insights['by_page'] : $this->insights->byPage(null, $days);

        $totalMessages = array_sum(array_map(fn ($d) => $d['incoming'] + $d['bot'] + $d['human'] + $d['failed'], $daily));

        return [
            'days' => $days,
            'periods' => self::PERIODS,
            'insights' => $insights,
            'kpis' => $this->kpis($insights['overview']['metrics'], $daily, $extra),
            'leaderboard' => $leaderboard,
            'hasPages' => MetaAccount::query()->exists(),
            'hasActivity' => $totalMessages > 0,
            'totalMessages' => $totalMessages,
            'selectedPageId' => $pageId,
        ];
    }

    /**
     * Per-day series InsightsService does not provide: conversations started, leads, AI (bot) failures.
     *
     * @return array{conversations: list<int>, leads: list<int>, ai_failures: list<int>}
     */
    private function extraDailySeries(?int $pageId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $dates = [];

        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $dates[$day->toDateString()] = 0;
        }

        $fill = function ($rows) use ($dates): array {
            foreach ($rows as $d => $n) {
                $key = substr((string) $d, 0, 10);

                if (array_key_exists($key, $dates)) {
                    $dates[$key] += (int) $n;
                }
            }

            return array_values($dates);
        };

        $conversations = fn () => Conversation::query()->forAccount($pageId);

        $started = $conversations()->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as d, COUNT(*) as n')->groupByRaw('DATE(created_at)')->toBase()->pluck('n', 'd');

        $leads = $conversations()->whereBetween('lead_captured_at', [$from, $to])
            ->selectRaw('DATE(lead_captured_at) as d, COUNT(*) as n')->groupByRaw('DATE(lead_captured_at)')->toBase()->pluck('n', 'd');

        $failures = Message::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('direction', MessageDirection::Outgoing)
            ->where('sender_type', SenderType::Bot)
            ->where('status', MessageStatus::Failed)
            ->when($pageId !== null, fn ($q) => $q->whereIn('conversation_id', Conversation::query()->select('id')->where('meta_account_id', $pageId)))
            ->selectRaw('DATE(created_at) as d, COUNT(*) as n')->groupByRaw('DATE(created_at)')->toBase()->pluck('n', 'd');

        return ['conversations' => $fill($started), 'leads' => $fill($leads), 'ai_failures' => $fill($failures)];
    }

    /**
     * KPI cards in display order.
     *
     * @return list<array{key: string, label: string, value: int|float|null, display: string, delta: ?string, trend: ?string,
     *   invert: bool, icon: string, tone: string, hint: ?string, spark: list<int|float>, href: ?string, sub: ?array}>
     */
    private function kpis(array $m, array $daily, array $extra): array
    {
        $col = fn (string $key) => array_map(fn ($d) => $d[$key], $daily);
        $links = $this->links();

        $failures = (int) ($m['ai_failures']['value'] ?? 0);

        return [
            $this->kpi('conversations', 'Conversations', $m['conversations_started'], 'messages', 'brand', $extra['conversations'],
                hint: number_format((int) $m['active_conversations']['value']).' active'),
            $this->kpi('incoming', 'Incoming messages', $m['incoming_messages'], 'inbox', 'brand', $col('incoming')),
            $this->kpi('bot_replies', 'Bot replies', $m['bot_replies'], 'bot', 'purple', $col('bot'), sub: [
                ['label' => 'AI', 'value' => (int) $m['ai_replies']['value']],
                ['label' => 'Automation', 'value' => (int) $m['automation_replies']['value']],
                ['label' => 'Fallback', 'value' => (int) $m['fallback_sends']['value']],
            ]),
            $this->kpi('human_replies', 'Human replies', $m['human_replies'], 'hand', 'neutral', $col('human')),
            $this->kpi('leads', 'Leads captured', $m['leads_captured'], 'sparkles', 'success', $extra['leads']),
            $this->kpi('first_response', 'Median first response', $m['first_response_median_seconds'], 'clock', 'warning', [],
                invert: true, display: self::duration($m['first_response_median_seconds']['value']),
                hint: $m['first_response_avg_seconds']['value'] !== null ? 'avg '.self::duration($m['first_response_avg_seconds']['value']) : null),
            $this->kpi('resolution', 'Bot resolution rate', $m['bot_resolution_rate'], 'check-circle', 'success', [],
                display: $m['bot_resolution_rate']['value'] === null ? '—' : rtrim(rtrim(number_format((float) $m['bot_resolution_rate']['value'], 1), '0'), '.').'%',
                points: true),
            $this->kpi('ai_failures', 'AI failures', $m['ai_failures'], 'alert-triangle', $failures > 0 ? 'danger' : 'neutral', $extra['ai_failures'],
                invert: true, href: $failures > 0 ? $links['failed'] : null,
                hint: $failures > 0 ? 'Review failed sends' : 'All replies delivered'),
        ];
    }

    private function kpi(string $key, string $label, array $metric, string $icon, string $tone, array $spark, bool $invert = false,
        ?string $display = null, ?string $hint = null, ?array $sub = null, ?string $href = null, bool $points = false): array
    {
        $value = $metric['value'];
        $delta = null;

        if ($points) {
            $delta = $metric['change'] === null ? null : self::signed((float) $metric['change'], ' pts');
        } elseif ($metric['change_pct'] !== null) {
            $delta = self::signed((float) $metric['change_pct'], '%');
        } elseif ($value !== null && $metric['previous'] === 0 && $value > 0) {
            $delta = 'New';
        } elseif ($value === 0 && $metric['previous'] === 0) {
            $delta = null;
        }

        $trend = match (true) {
            $delta === null => null,
            $delta === 'New' => 'up',
            str_starts_with($delta, '-') => 'down',
            str_starts_with($delta, '+') => 'up',
            default => 'flat',
        };

        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'display' => $display ?? ($value === null ? '—' : self::compact($value)),
            'delta' => $delta,
            'trend' => $trend,
            'invert' => $invert,
            'icon' => $icon,
            'tone' => $tone,
            'hint' => $hint,
            'spark' => $spark,
            'sub' => $sub,
            'href' => $href,
        ];
    }

    /** Headline counters (kept for the JSON feed and backwards-compatible tests). */
    private function stats(): array
    {
        return [
            'accounts' => MetaAccount::count(),
            'active_accounts' => MetaAccount::active()->count(),
            'open_conversations' => $this->currentPage->scope(Conversation::query())->where('status', 'open')->count(),
            'needs_human' => $this->currentPage->scope(Conversation::query())->where('status', 'open')->where('human_takeover', true)->count(),
            'failed_outgoing_24h' => Message::query()
                ->where('direction', MessageDirection::Outgoing)
                ->where('status', MessageStatus::Failed)
                ->where('created_at', '>=', now()->subDay())
                ->when($this->currentPage->id(), fn ($q, $id) => $q->whereIn('conversation_id', Conversation::query()->select('id')->where('meta_account_id', $id)))
                ->count(),
            'webhooks_pending' => WebhookEvent::where('status', 'pending')->count(),
            'webhooks_stuck' => WebhookEvent::where('status', 'pending')->where('created_at', '<', now()->subMinutes(5))->count(),
            'webhooks_failed' => WebhookEvent::where('status', 'failed')->count(),
            'webhooks_failed_24h' => WebhookEvent::where('status', 'failed')->where('created_at', '>=', now()->subDay())->count(),
            'last_webhook_at' => WebhookEvent::query()->max('created_at'),
        ];
    }

    /** @return list<array{key: string, label: string, ok: bool, warn?: bool, detail: string, hint: string, href?: ?string}> */
    private function health(array $stats): array
    {
        $provider = AiProviderFactory::defaultProvider();
        $providerRow = collect($this->aiProviderStatus())->firstWhere('key', $provider);
        $queue = (string) config('queue.default');

        return [
            ['key' => 'app_id', 'label' => 'META_APP_ID', 'ok' => filled(config('meta.app_id')), 'detail' => filled(config('meta.app_id')) ? 'Set' : 'Missing', 'hint' => 'Needed for token exchange (meta:connect).'],
            ['key' => 'app_secret', 'label' => 'META_APP_SECRET', 'ok' => filled(config('meta.app_secret')), 'detail' => filled(config('meta.app_secret')) ? 'Set' : 'Missing', 'hint' => 'Needed to verify webhook signatures.'],
            ['key' => 'verify_token', 'label' => 'META_VERIFY_TOKEN', 'ok' => filled(config('meta.verify_token')), 'detail' => filled(config('meta.verify_token')) ? 'Set' : 'Missing', 'hint' => 'Must match the Verify Token entered in the Meta dashboard.'],
            ['key' => 'signature', 'label' => 'Webhook signature check', 'ok' => (bool) config('meta.verify_signature'), 'detail' => config('meta.verify_signature') ? 'Enforced' : 'Off', 'hint' => 'META_VERIFY_SIGNATURE should be true in production.'],
            ['key' => 'ai', 'label' => 'AI provider: '.($providerRow['label'] ?? $provider), 'ok' => (bool) ($providerRow['configured'] ?? false), 'detail' => ($providerRow['configured'] ?? false) ? 'Configured' : 'Missing', 'hint' => 'API key for the active provider (OPENAI_API_KEY or ANTHROPIC_API_KEY) is needed for AI replies.'],
            ['key' => 'queue', 'label' => 'Queue connection: '.$queue, 'ok' => $queue !== 'sync', 'detail' => $queue !== 'sync' ? 'Async' : 'Sync', 'hint' => 'Use database/redis and run `php artisan queue:work` so webhooks return fast.'],
            ['key' => 'account', 'label' => 'Active Meta account', 'ok' => $stats['active_accounts'] > 0, 'detail' => $stats['active_accounts'].' of '.$stats['accounts'].' active', 'hint' => 'Connect a page under Pages & Channels or run `php artisan meta:connect`.', 'href' => $this->links()['connect']],
            ['key' => 'webhooks_pending', 'label' => 'Webhook queue', 'ok' => $stats['webhooks_stuck'] === 0, 'detail' => $stats['webhooks_pending'].' pending', 'hint' => 'Events pending for more than 5 minutes mean the queue worker is not running.', 'href' => $this->links()['webhooks_pending']],
            ['key' => 'webhooks_failed', 'label' => 'Webhook processing', 'ok' => $stats['webhooks_failed_24h'] === 0, 'warn' => $stats['webhooks_failed'] > 0 && $stats['webhooks_failed_24h'] === 0, 'detail' => $stats['webhooks_failed'].' failed', 'hint' => 'Failed events in the last 24 hours need a look.', 'href' => $this->links()['webhooks_failed']],
        ];
    }

    /** @return list<array{key: string, label: string, configured: bool, default: bool}> */
    private function aiProviderStatus(): array
    {
        try {
            $default = AiProviderFactory::defaultProvider();

            return array_map(fn (array $p) => [
                'key' => $p['key'], 'label' => $p['label'], 'configured' => $p['configured'],
                'default' => $p['key'] === $default, 'model' => $p['default_model'],
            ], $this->aiProviders->available());
        } catch (Throwable) {
            return [];
        }
    }

    /** Setup steps with auto-detected completion. */
    private function checklist(): array
    {
        $links = $this->links();
        $aiConfigured = collect($this->aiProviderStatus())->contains('configured', true);
        $global = BotSetting::query()->whereNull('meta_account_id')->first();
        $customized = BotSetting::query()->whereNotNull('meta_account_id')->exists()
            || ($global !== null && (filled($global->system_prompt) || filled($global->business_info) || filled($global->faqs)
                || filled($global->offers) || $global->updated_at?->gt($global->created_at)));

        $steps = [
            ['key' => 'credentials', 'title' => 'Add your Meta app credentials', 'description' => 'Set META_APP_ID and META_APP_SECRET in .env, then run php artisan config:clear.',
                'done' => filled(config('meta.app_id')) && filled(config('meta.app_secret')), 'href' => null, 'cta' => null],
            ['key' => 'page', 'title' => 'Connect a Facebook Page or Instagram account', 'description' => 'Paste a user token and pick the pages the bot should answer.',
                'done' => MetaAccount::query()->exists(), 'href' => $links['connect'], 'cta' => 'Connect page'],
            ['key' => 'webhook', 'title' => 'Subscribe the webhook', 'description' => 'Add the callback URL + verify token in the Meta dashboard. Done once the first event arrives.',
                'done' => WebhookEvent::query()->exists() || Message::query()->where('direction', MessageDirection::Incoming)->exists(), 'href' => '#connection-details', 'cta' => 'Show details'],
            ['key' => 'ai', 'title' => 'Configure an AI provider', 'description' => 'Add OPENAI_API_KEY or ANTHROPIC_API_KEY so the bot can write replies.',
                'done' => $aiConfigured, 'href' => $links['bot'], 'cta' => 'Open Bot Studio'],
            ['key' => 'bot', 'title' => 'Teach the bot about your business', 'description' => 'Write the prompt, business info and FAQs in Bot Studio.',
                'done' => $customized, 'href' => $links['bot'], 'cta' => 'Customize bot'],
            ['key' => 'reply', 'title' => 'Send your first reply', 'description' => 'Message your page from a test account and watch the bot answer.',
                'done' => Message::query()->where('direction', MessageDirection::Outgoing)->where('status', MessageStatus::Sent)->exists(), 'href' => $links['live_chat'], 'cta' => 'Open Live Chat'],
        ];

        $done = count(array_filter($steps, fn ($s) => $s['done']));

        return ['steps' => $steps, 'done' => $done, 'total' => count($steps), 'percent' => (int) round($done / count($steps) * 100), 'complete' => $done === count($steps)];
    }

    /** @return array<string, string> */
    private function links(): array
    {
        $route = fn (string $name, array $params = [], string $fallback = '#') => Route::has($name) ? route($name, $params) : $fallback;

        return [
            'dashboard' => route('admin.dashboard'),
            'data' => route('admin.dashboard.data'),
            'live_chat' => $route('admin.conversations.index'),
            'bot' => $route('admin.bot-settings.edit'),
            'connect' => $route('admin.meta-accounts.connect'),
            'pages' => $route('admin.meta-accounts.index'),
            'page_switch' => $route('admin.page-switch'),
            'webhooks' => $route('admin.webhook-events.index'),
            'webhooks_failed' => $route('admin.webhook-events.index', ['status' => 'failed']),
            'webhooks_pending' => $route('admin.webhook-events.index', ['status' => 'pending']),
            'failed' => $route('admin.webhook-events.index', ['status' => 'failed']),
        ];
    }

    // ---------------------------------------------------------------------------------------------

    /** 4 → "4s", 72 → "1m 12s", 3900 → "1h 5m", null → "—". */
    public static function duration(int|float|null $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        $s = (int) round($seconds);

        return match (true) {
            $s < 60 => $s.'s',
            $s < 3600 => intdiv($s, 60).'m'.($s % 60 ? ' '.($s % 60).'s' : ''),
            $s < 86400 => intdiv($s, 3600).'h'.(intdiv($s % 3600, 60) ? ' '.intdiv($s % 3600, 60).'m' : ''),
            default => intdiv($s, 86400).'d'.(intdiv($s % 86400, 3600) ? ' '.intdiv($s % 86400, 3600).'h' : ''),
        };
    }

    /** 1284 → "1,284", 12900 → "12.9K", 4200000 → "4.2M". */
    public static function compact(int|float $n): string
    {
        return match (true) {
            abs($n) >= 1_000_000 => rtrim(rtrim(number_format($n / 1_000_000, 1), '0'), '.').'M',
            abs($n) >= 100_000 => rtrim(rtrim(number_format($n / 1_000, 1), '0'), '.').'K',
            default => number_format($n, is_float($n) && floor($n) != $n ? 1 : 0),
        };
    }

    private static function signed(float $n, string $unit): string
    {
        $formatted = rtrim(rtrim(number_format(abs($n), 1), '0'), '.');

        return match (true) {
            $n > 0 => '+'.$formatted.$unit,
            $n < 0 => '-'.$formatted.$unit,
            default => '0'.$unit,
        };
    }
}
