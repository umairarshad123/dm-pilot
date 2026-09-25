<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MetaAccount;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WebhookEventController extends Controller
{
    public const STATUSES = ['pending', 'processed', 'ignored', 'failed'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'object' => ['nullable', 'string', 'max:30'],
        ]);

        $events = WebhookEvent::query()
            ->select(['id', 'object', 'status', 'messages_count', 'error', 'processed_at', 'created_at'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['object'] ?? null, fn ($q, $v) => $q->where('object', $v))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $counts = WebhookEvent::query()
            ->when($filters['object'] ?? null, fn ($q, $v) => $q->where('object', $v))
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('admin.webhook-events.index', [
            'events' => $events,
            'filters' => $filters,
            'counts' => $counts,
            'objects' => WebhookEvent::query()->distinct()->orderBy('object')->pluck('object')->filter()->values(),
            'last24h' => WebhookEvent::where('created_at', '>=', now()->subDay())->count(),
        ]);
    }

    public function show(WebhookEvent $webhookEvent): View
    {
        $json = (string) json_encode($webhookEvent->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $entryIds = collect((array) data_get($webhookEvent->payload, 'entry', []))->pluck('id')->filter()->map(fn ($id) => (string) $id)->unique()->values();
        $accounts = $entryIds->isEmpty() ? collect() : MetaAccount::query()
            ->where(fn ($q) => $q->whereIn('page_id', $entryIds)->orWhereIn('instagram_account_id', $entryIds))
            ->get(['id', 'platform', 'page_name', 'page_id', 'instagram_account_id']);

        $messaging = collect((array) data_get($webhookEvent->payload, 'entry', []))
            ->flatMap(fn ($entry) => (array) ($entry['messaging'] ?? []))->count();

        return view('admin.webhook-events.show', [
            'event' => $webhookEvent,
            'json' => $json,
            'highlighted' => self::highlight($json),
            'entryIds' => $entryIds,
            'accounts' => $accounts,
            'messagingCount' => $messaging,
            'previous' => WebhookEvent::where('id', '<', $webhookEvent->id)->max('id'),
            'next' => WebhookEvent::where('id', '>', $webhookEvent->id)->min('id'),
        ]);
    }

    /**
     * HTML-escape pretty JSON, then wrap keys / strings / numbers / literals in coloured spans.
     * Everything is escaped before any markup is added, so the output is safe to print raw.
     */
    public static function highlight(string $json): string
    {
        $escaped = e($json);

        return (string) preg_replace_callback(
            '/(&quot;(?:\\\\(?:&quot;|.)|(?!&quot;)[^\\\\])*?&quot;)(\s*:)?|\b(true|false|null)\b|(-?\b\d+(?:\.\d+)?(?:[eE][+-]?\d+)?\b)/u',
            function (array $m): string {
                if (($m[1] ?? '') !== '') {
                    $class = ($m[2] ?? '') !== '' ? 'text-sky-300' : 'text-emerald-300';

                    return '<span class="'.$class.'">'.$m[1].'</span>'.($m[2] ?? '');
                }
                if (($m[3] ?? '') !== '') {
                    return '<span class="text-violet-300">'.$m[3].'</span>';
                }

                return '<span class="text-amber-300">'.$m[4].'</span>';
            },
            $escaped,
        );
    }
}
