<?php

namespace App\Services\Insights;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\Platform;
use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetaAccount;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard analytics. Every method takes an optional meta_accounts.id filter (null = all pages) and a period:
 * an int N = the last N days including today, or a CarbonPeriod (start/end dates, inclusive days).
 *
 * Message times use messages.created_at (always set; for incoming messages ≈ Meta timestamp).
 * Grouped queries only; date bucketing uses DATE() (MySQL/MariaDB/SQLite) and per-driver hour/weekday
 * expressions. Times are in the app timezone (stored timestamps are app-timezone).
 */
class InsightsService
{
    public const DEFAULT_DAYS = 14;

    /**
     * Headline numbers + comparison with the previous period of the same length.
     *
     * @return array{
     *   period: array{from: string, to: string, days: int, previous_from: string, previous_to: string},
     *   metrics: array<string, array{value: int|float|null, previous: int|float|null, change: int|float|null, change_pct: float|null}>
     * }
     *   metrics keys: conversations_started, active_conversations, incoming_messages, bot_replies, ai_replies,
     *   automation_replies, fallback_sends, human_replies, ai_failures, leads_captured,
     *   first_response_median_seconds, first_response_avg_seconds, bot_resolution_rate (percent 0–100)
     */
    public function overview(?int $metaAccountId = null, CarbonPeriod|int $days = self::DEFAULT_DAYS): array
    {
        [$from, $to] = $this->range($days);
        $length = (int) $from->diffInDays($to->addSecond());
        [$prevFrom, $prevTo] = [$from->subDays($length), $from->subSecond()];

        $current = $this->metrics($metaAccountId, $from, $to);
        $previous = $this->metrics($metaAccountId, $prevFrom, $prevTo);
        $metrics = [];

        foreach ($current as $key => $value) {
            $prev = $previous[$key];
            $change = match (true) {
                $value === null || $prev === null => null,
                is_int($value) && is_int($prev) => $value - $prev,
                default => round($value - $prev, 1),
            };

            $metrics[$key] = [
                'value' => $value,
                'previous' => $prev,
                'change' => $change,
                'change_pct' => $value !== null && $prev !== null && $prev != 0 ? round(($value - $prev) / abs($prev) * 100, 1) : null,
            ];
        }

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => $length,
                'previous_from' => $prevFrom->toDateString(),
                'previous_to' => $prevTo->toDateString(),
            ],
            'metrics' => $metrics,
        ];
    }

    /**
     * Zero-filled per-day counts for charts, oldest first.
     *
     * @return list<array{date: string, incoming: int, bot: int, human: int, failed: int}>
     *                                                                                     bot/human = sent outgoing messages; failed = outgoing messages with status failed
     */
    public function dailySeries(?int $metaAccountId = null, CarbonPeriod|int $days = self::DEFAULT_DAYS): array
    {
        [$from, $to] = $this->range($days);

        $rows = $this->messages($metaAccountId, $from, $to)
            ->selectRaw('DATE(messages.created_at) as d, messages.direction, messages.sender_type, messages.status, COUNT(*) as c')
            ->groupByRaw('DATE(messages.created_at), messages.direction, messages.sender_type, messages.status')
            ->toBase()->get();

        $series = [];

        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $series[$day->toDateString()] = ['date' => $day->toDateString(), 'incoming' => 0, 'bot' => 0, 'human' => 0, 'failed' => 0];
        }

        foreach ($rows as $row) {
            $date = substr((string) $row->d, 0, 10);

            if (! isset($series[$date]) || ($bucket = $this->bucket($row)) === null) {
                continue;
            }

            $series[$date][$bucket] += (int) $row->c;
        }

        return array_values($series);
    }

    /**
     * Per connected account (every account, zero-filled; only one when filtered), busiest first.
     *
     * @return list<array{meta_account_id: int, name: string, platform: string, active: bool, conversations_started: int,
     *   active_conversations: int, incoming: int, bot: int, human: int, failed: int, leads_captured: int}>
     */
    public function byPage(?int $metaAccountId = null, CarbonPeriod|int $days = self::DEFAULT_DAYS): array
    {
        [$from, $to] = $this->range($days);

        $accounts = MetaAccount::query()
            ->when($metaAccountId !== null, fn ($q) => $q->whereKey($metaAccountId))
            ->orderBy('id')->get(['id', 'platform', 'page_name', 'page_id', 'instagram_account_id', 'active']);

        $messageCounts = $this->messages($metaAccountId, $from, $to)
            ->join('conversations as c', 'c.id', '=', 'messages.conversation_id')
            ->selectRaw('c.meta_account_id as k, messages.direction, messages.sender_type, messages.status, COUNT(*) as c')
            ->groupBy('c.meta_account_id', 'messages.direction', 'messages.sender_type', 'messages.status')
            ->toBase()->get();

        $active = $this->messages($metaAccountId, $from, $to)
            ->join('conversations as c', 'c.id', '=', 'messages.conversation_id')
            ->selectRaw('c.meta_account_id as k, COUNT(DISTINCT messages.conversation_id) as n')
            ->groupBy('c.meta_account_id')->toBase()->pluck('n', 'k');

        $started = $this->conversations($metaAccountId)->whereBetween('created_at', [$from, $to])
            ->selectRaw('meta_account_id as k, COUNT(*) as n')->groupBy('meta_account_id')->toBase()->pluck('n', 'k');

        $leads = $this->conversations($metaAccountId)->whereBetween('lead_captured_at', [$from, $to])
            ->selectRaw('meta_account_id as k, COUNT(*) as n')->groupBy('meta_account_id')->toBase()->pluck('n', 'k');

        $result = [];

        foreach ($accounts as $account) {
            $result[$account->id] = [
                'meta_account_id' => $account->id,
                'name' => (string) ($account->page_name ?: $account->ownExternalId() ?: 'Account #'.$account->id),
                'platform' => $account->platform->value,
                'active' => (bool) $account->active,
                'conversations_started' => (int) ($started[$account->id] ?? 0),
                'active_conversations' => (int) ($active[$account->id] ?? 0),
                'incoming' => 0, 'bot' => 0, 'human' => 0, 'failed' => 0,
                'leads_captured' => (int) ($leads[$account->id] ?? 0),
            ];
        }

        foreach ($messageCounts as $row) {
            if (isset($result[$row->k]) && ($bucket = $this->bucket($row)) !== null) {
                $result[$row->k][$bucket] += (int) $row->c;
            }
        }

        $result = array_values($result);
        usort($result, fn ($a, $b) => [$b['incoming'] + $b['bot'] + $b['human'], $a['meta_account_id']] <=> [$a['incoming'] + $a['bot'] + $a['human'], $b['meta_account_id']]);

        return $result;
    }

    /**
     * Facebook (Messenger) vs Instagram.
     *
     * @return array{facebook: array{conversations_started: int, active_conversations: int, incoming: int, bot: int, human: int, failed: int},
     *   instagram: array{conversations_started: int, active_conversations: int, incoming: int, bot: int, human: int, failed: int}}
     */
    public function byChannel(?int $metaAccountId = null, CarbonPeriod|int $days = self::DEFAULT_DAYS): array
    {
        [$from, $to] = $this->range($days);
        $empty = ['conversations_started' => 0, 'active_conversations' => 0, 'incoming' => 0, 'bot' => 0, 'human' => 0, 'failed' => 0];
        $result = [];

        foreach (Platform::cases() as $platform) {
            $result[$platform->value] = $empty;
        }

        $rows = $this->messages($metaAccountId, $from, $to)
            ->join('conversations as c', 'c.id', '=', 'messages.conversation_id')
            ->selectRaw('c.platform as k, messages.direction, messages.sender_type, messages.status, COUNT(*) as c')
            ->groupBy('c.platform', 'messages.direction', 'messages.sender_type', 'messages.status')
            ->toBase()->get();

        foreach ($rows as $row) {
            if (isset($result[$row->k]) && ($bucket = $this->bucket($row)) !== null) {
                $result[$row->k][$bucket] += (int) $row->c;
            }
        }

        $active = $this->messages($metaAccountId, $from, $to)
            ->join('conversations as c', 'c.id', '=', 'messages.conversation_id')
            ->selectRaw('c.platform as k, COUNT(DISTINCT messages.conversation_id) as n')
            ->groupBy('c.platform')->toBase()->pluck('n', 'k');

        $started = $this->conversations($metaAccountId)->whereBetween('created_at', [$from, $to])
            ->selectRaw('platform as k, COUNT(*) as n')->groupBy('platform')->toBase()->pluck('n', 'k');

        foreach ($result as $key => $_) {
            $result[$key]['active_conversations'] = (int) ($active[$key] ?? 0);
            $result[$key]['conversations_started'] = (int) ($started[$key] ?? 0);
        }

        return $result;
    }

    /**
     * Incoming customer messages by weekday × hour.
     *
     * @return array{days: list<string>, hours: list<int>, matrix: list<list<int>>, max: int, total: int}
     *                                                                                                    matrix[0] = Monday … matrix[6] = Sunday; matrix[d][h] = count for hour h (0–23)
     */
    public function hourlyHeatmap(?int $metaAccountId = null, CarbonPeriod|int $days = self::DEFAULT_DAYS): array
    {
        [$from, $to] = $this->range($days);
        [$dowExpr, $hourExpr] = $this->dowHourExpressions('messages.created_at');

        $rows = $this->messages($metaAccountId, $from, $to)
            ->where('messages.direction', MessageDirection::Incoming)
            ->selectRaw("{$dowExpr} as dow, {$hourExpr} as hr, COUNT(*) as c")
            ->groupByRaw("{$dowExpr}, {$hourExpr}")
            ->toBase()->get();

        $matrix = array_fill(0, 7, array_fill(0, 24, 0));

        foreach ($rows as $row) {
            $sundayFirst = (int) $row->dow; // 0 = Sunday
            $monFirst = ($sundayFirst + 6) % 7;
            $hour = (int) $row->hr;

            if ($hour >= 0 && $hour < 24) {
                $matrix[$monFirst][$hour] += (int) $row->c;
            }
        }

        $flat = array_merge(...$matrix);

        return [
            'days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'hours' => range(0, 23),
            'matrix' => $matrix,
            'max' => max($flat),
            'total' => array_sum($flat),
        ];
    }

    /**
     * Latest messages across conversations (newest first).
     *
     * @return list<array{id: int, conversation_id: int, contact_name: string, contact_initials: string,
     *   profile_pic_url: ?string, platform: ?string, meta_account_id: int, page_name: ?string, direction: string,
     *   sender_type: string, status: string, source: ?string, excerpt: string, has_attachments: bool,
     *   at: Carbon}>
     */
    public function recentActivity(?int $metaAccountId = null, int $limit = 15): array
    {
        return Message::query()
            ->when($metaAccountId !== null, fn (Builder $q) => $q->whereIn('conversation_id', Conversation::query()->select('id')->where('meta_account_id', $metaAccountId)))
            ->with('conversation.metaAccount:id,platform,page_name,page_id,instagram_account_id')
            ->latest('id')
            ->limit(max(1, min($limit, 100)))
            ->get()
            ->map(fn (Message $m) => [
                'id' => $m->id,
                'conversation_id' => $m->conversation_id,
                'contact_name' => $m->conversation?->displayName() ?? 'Unknown',
                'contact_initials' => $m->conversation?->initials() ?? '?',
                'profile_pic_url' => $m->conversation?->profile_pic_url,
                'platform' => $m->conversation?->platform?->value,
                'meta_account_id' => (int) $m->conversation?->meta_account_id,
                'page_name' => $m->conversation?->metaAccount?->page_name,
                'direction' => $m->direction->value,
                'sender_type' => $m->sender_type->value,
                'status' => $m->status->value,
                'source' => $m->sourceMarker(),
                'excerpt' => Conversation::excerpt($m->body, 140) ?: ($m->attachments ? '[attachment]' : ''),
                'has_attachments' => ! empty($m->attachments),
                'at' => $m->created_at,
            ])
            ->all();
    }

    /** Everything a dashboard needs in one call (same keys as the individual methods). */
    public function dashboard(?int $metaAccountId = null, CarbonPeriod|int $days = self::DEFAULT_DAYS, int $recent = 15): array
    {
        return [
            'overview' => $this->overview($metaAccountId, $days),
            'daily' => $this->dailySeries($metaAccountId, $days),
            'by_page' => $this->byPage($metaAccountId, $days),
            'by_channel' => $this->byChannel($metaAccountId, $days),
            'heatmap' => $this->hourlyHeatmap($metaAccountId, $days),
            'recent' => $this->recentActivity($metaAccountId, $recent),
        ];
    }

    // ---------------------------------------------------------------------------------------------

    /** @return array<string, int|float|null> */
    private function metrics(?int $metaAccountId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $counts = $this->messages($metaAccountId, $from, $to)
            ->selectRaw('messages.direction, messages.sender_type, messages.status, COUNT(*) as c')
            ->groupBy('messages.direction', 'messages.sender_type', 'messages.status')
            ->toBase()->get();

        $totals = ['incoming' => 0, 'bot' => 0, 'human' => 0, 'failed' => 0];

        foreach ($counts as $row) {
            if (($bucket = $this->bucket($row)) !== null) {
                $totals[$bucket] += (int) $row->c;
            }
        }

        $aiFailures = (int) $this->messages($metaAccountId, $from, $to)
            ->where('messages.direction', MessageDirection::Outgoing)
            ->where('messages.sender_type', SenderType::Bot)
            ->where('messages.status', MessageStatus::Failed)
            ->count();

        $sentBot = fn () => $this->messages($metaAccountId, $from, $to)
            ->where('messages.direction', MessageDirection::Outgoing)
            ->where('messages.sender_type', SenderType::Bot)
            ->where('messages.status', MessageStatus::Sent);

        $automation = (int) $sentBot()->where('messages.payload->source', 'automation')->count();
        $fallback = (int) $sentBot()->where(fn ($q) => $q->where('messages.error', 'like', 'AI failed; fallback sent%')
            ->orWhere('messages.payload->source', 'fallback'))->count();

        $active = (int) $this->messages($metaAccountId, $from, $to)->distinct()->count('messages.conversation_id');
        $withHuman = (int) $this->messages($metaAccountId, $from, $to)
            ->where('messages.direction', MessageDirection::Outgoing)
            ->where('messages.sender_type', SenderType::Human)
            ->distinct()->count('messages.conversation_id');

        [$median, $avg] = $this->firstResponse($metaAccountId, $from, $to);

        return [
            'conversations_started' => (int) $this->conversations($metaAccountId)->whereBetween('created_at', [$from, $to])->count(),
            'active_conversations' => $active,
            'incoming_messages' => $totals['incoming'],
            'bot_replies' => $totals['bot'],
            'ai_replies' => max(0, $totals['bot'] - $automation - $fallback),
            'automation_replies' => $automation,
            'fallback_sends' => $fallback,
            'human_replies' => $totals['human'],
            'ai_failures' => $aiFailures,
            'leads_captured' => (int) $this->conversations($metaAccountId)->whereBetween('lead_captured_at', [$from, $to])->count(),
            'first_response_median_seconds' => $median,
            'first_response_avg_seconds' => $avg,
            'bot_resolution_rate' => $active > 0 ? round(($active - $withHuman) / $active * 100, 1) : null,
        ];
    }

    /** @return array{0: ?int, 1: ?int} median + average seconds from customer message to the bot reply */
    private function firstResponse(?int $metaAccountId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->messages($metaAccountId, $from, $to)
            ->join('messages as r', 'r.in_reply_to_id', '=', 'messages.id')
            ->where('messages.direction', MessageDirection::Incoming)
            ->where('r.sender_type', SenderType::Bot)
            ->where('r.status', MessageStatus::Sent)
            ->whereNotNull('r.sent_at')
            ->toBase()
            ->get(['messages.sent_at as in_at', 'messages.created_at as in_created', 'r.sent_at as out_at']);

        $diffs = [];

        foreach ($rows as $row) {
            $in = Carbon::parse($row->in_at ?? $row->in_created);
            $diffs[] = max(0, Carbon::parse($row->out_at)->getTimestamp() - $in->getTimestamp());
        }

        if ($diffs === []) {
            return [null, null];
        }

        sort($diffs);
        $n = count($diffs);
        $median = $n % 2 ? $diffs[intdiv($n, 2)] : ($diffs[$n / 2 - 1] + $diffs[$n / 2]) / 2;

        return [(int) round($median), (int) round(array_sum($diffs) / $n)];
    }

    /** Map a (direction, sender_type, status) group to a series bucket. */
    private function bucket(object $row): ?string
    {
        $direction = $row->direction instanceof MessageDirection ? $row->direction->value : (string) $row->direction;
        $status = (string) $row->status;

        if ($direction === MessageDirection::Incoming->value) {
            return 'incoming';
        }

        if ($status === MessageStatus::Failed->value) {
            return 'failed';
        }

        if ($status !== MessageStatus::Sent->value) {
            return null; // pending / skipped
        }

        return match ((string) $row->sender_type) {
            SenderType::Bot->value => 'bot',
            SenderType::Human->value => 'human',
            default => null,
        };
    }

    private function messages(?int $metaAccountId, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Message::query()
            ->whereBetween('messages.created_at', [$from, $to])
            ->when($metaAccountId !== null, fn (Builder $q) => $q->whereIn(
                'messages.conversation_id',
                Conversation::query()->select('id')->where('meta_account_id', $metaAccountId),
            ));
    }

    private function conversations(?int $metaAccountId): Builder
    {
        return Conversation::query()->forAccount($metaAccountId);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} inclusive start-of-day .. end-of-day */
    public function range(CarbonPeriod|int $days): array
    {
        if ($days instanceof CarbonPeriod) {
            $from = CarbonImmutable::parse($days->getStartDate())->startOfDay();
            $to = CarbonImmutable::parse($days->getEndDate() ?? Carbon::now())->endOfDay();

            return [$from, $to->lt($from) ? $from->endOfDay() : $to];
        }

        $days = max(1, min($days, 366));
        $now = CarbonImmutable::now();

        return [$now->subDays($days - 1)->startOfDay(), $now->endOfDay()];
    }

    /** @return array{0: string, 1: string} SQL for weekday (0 = Sunday) and hour of a timestamp column */
    private function dowHourExpressions(string $column): array
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => ["CAST(strftime('%w', {$column}) AS INTEGER)", "CAST(strftime('%H', {$column}) AS INTEGER)"],
            'pgsql' => ["EXTRACT(DOW FROM {$column})", "EXTRACT(HOUR FROM {$column})"],
            'sqlsrv' => ["(DATEPART(WEEKDAY, {$column}) - 1)", "DATEPART(HOUR, {$column})"],
            default => ["(DAYOFWEEK({$column}) - 1)", "HOUR({$column})"], // mysql, mariadb
        };
    }
}
