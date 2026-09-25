<?php

namespace App\Services\Contacts;

use App\Enums\LeadStage;
use App\Enums\MessageDirection;
use App\Enums\Platform;
use App\Jobs\FetchContactProfile;
use App\Models\Conversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Contacts (CRM) listing, editing and export. A contact is a Conversation row.
 *
 * Filters (all optional, unknown keys ignored):
 *   page_id    int     meta_accounts.id
 *   platform   string  facebook|instagram
 *   search     string  name / @username / email / phone / PSID
 *   tag        string
 *   lead_stage string  LeadStage value ("new" also matches NULL)
 *   has_email  bool
 *   has_phone  bool
 *   unread     bool    only contacts with unread messages
 *   sort       string  recent (default: last_message_at desc) | oldest | name | created | unread
 */
class ContactService
{
    public const SORTS = ['recent', 'oldest', 'name', 'created', 'unread'];

    public const EXPORT_COLUMNS = [
        'Name', 'Username', 'Platform', 'Page', 'Email', 'Phone', 'Stage', 'Tags',
        'First message at', 'Last message at', 'Incoming messages', 'Outgoing messages', 'Total messages',
    ];

    /**
     * Paginated contacts for the Contacts table. Each item is a Conversation with `metaAccount` loaded and
     * `messages_count` set.
     *
     * @return LengthAwarePaginator<Conversation>
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->query($filters)
            ->with('metaAccount:id,platform,page_name,page_id,instagram_account_id')
            ->withCount('messages')
            ->paginate(max(1, min($perPage, 200)))
            ->withQueryString();
    }

    /** Filtered + sorted base query (reusable for counts / bulk actions). */
    public function query(array $filters = []): Builder
    {
        $query = Conversation::query();

        if (filled($filters['page_id'] ?? null)) {
            $query->forAccount((int) $filters['page_id']);
        }

        if (($platform = Platform::tryFrom((string) ($filters['platform'] ?? ''))) !== null) {
            $query->where('platform', $platform);
        }

        $query->search($filters['search'] ?? null);

        if (filled($filters['tag'] ?? null)) {
            $query->withTag((string) $filters['tag']);
        }

        if (filled($filters['lead_stage'] ?? null)) {
            $query->leadStage((string) $filters['lead_stage']);
        }

        foreach (['has_email' => 'email', 'has_phone' => 'phone'] as $key => $column) {
            if (array_key_exists($key, $filters) && $filters[$key] !== null && $filters[$key] !== '') {
                filter_var($filters[$key], FILTER_VALIDATE_BOOLEAN)
                    ? $query->whereNotNull($column)->where($column, '!=', '')
                    : $query->where(fn (Builder $q) => $q->whereNull($column)->orWhere($column, ''));
            }
        }

        if (filter_var($filters['unread'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('unread_count', '>', 0);
        }

        return $this->sort($query, (string) ($filters['sort'] ?? 'recent'));
    }

    /**
     * Distinct tags in use, alphabetically. With counts: ['vip' => 12, …].
     *
     * @return list<string>
     */
    public function allTags(?int $pageId = null): array
    {
        return array_keys($this->tagCounts($pageId));
    }

    /** @return array<string, int> tag => number of contacts, sorted by tag */
    public function tagCounts(?int $pageId = null): array
    {
        $counts = [];

        Conversation::query()->forAccount($pageId)->whereNotNull('tags')->select(['id', 'tags'])
            ->lazyById(500)
            ->each(function (Conversation $c) use (&$counts) {
                foreach ($c->tagList() as $tag) {
                    $counts[$tag] = ($counts[$tag] ?? 0) + 1;
                }
            });

        ksort($counts, SORT_NATURAL);

        return $counts;
    }

    /** Validation rules for update() (all fields optional → partial updates). */
    public static function rules(): array
    {
        return [
            'customer_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40', 'regex:/^\+?[\d\s\-().]{6,39}$/'],
            'lead_stage' => ['sometimes', 'nullable', Rule::enum(LeadStage::class)],
            'tags' => ['sometimes', 'nullable', 'array', 'max:50'],
            'tags.*' => ['string', 'max:50'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * Validate and apply a (partial) contact edit. Only keys present in $fields are touched.
     * Tags are normalized (lowercase, trimmed, unique); email lowercased; phone normalized to "+digits"; empty strings stored as NULL.
     *
     * @throws ValidationException
     */
    public function update(Conversation $conversation, array $fields): Conversation
    {
        if (isset($fields['tags']) && is_string($fields['tags'])) {
            $fields['tags'] = explode(',', $fields['tags']); // "vip, wholesale" from a text input
        }

        $data = Validator::make($fields, self::rules())->validate();
        $changes = [];

        foreach (Arr::except($data, ['tags']) as $key => $value) {
            $value = is_string($value) ? trim($value) : $value;
            $changes[$key] = $value === '' ? null : $value;
        }

        if (isset($changes['email'])) {
            $changes['email'] = mb_strtolower($changes['email']);
        }

        if (isset($changes['phone'])) {
            $changes['phone'] = self::normalizePhone($changes['phone']);
        }

        if (array_key_exists('tags', $data)) {
            $tags = Conversation::normalizeTags($data['tags'] ?? []);
            $changes['tags'] = $tags === [] ? null : $tags;
        }

        $conversation->forceFill($changes)->save();

        return $conversation;
    }

    /** Queue a profile re-fetch from Meta (force = ignore the TTL). */
    public function refreshProfile(Conversation $conversation, bool $force = true): void
    {
        FetchContactProfile::dispatch($conversation->id, $force);
    }

    /** Stream the filtered contacts as CSV (UTF-8 with BOM for Excel). */
    public function exportCsv(array $filters = [], ?string $filename = null): StreamedResponse
    {
        $filename ??= 'contacts-'.Carbon::now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::EXPORT_COLUMNS, escape: '');

            foreach ($this->exportRows($filters) as $row) {
                fputcsv($out, array_map([self::class, 'csvSafe'], $row), escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The export rows (same order as EXPORT_COLUMNS), streamed in chunks.
     *
     * @return \Generator<int, list<string|int>>
     */
    public function exportRows(array $filters = []): \Generator
    {
        $query = $this->query($filters)
            ->with('metaAccount:id,platform,page_name,page_id,instagram_account_id')
            ->withCount([
                'messages',
                'messages as incoming_messages_count' => fn (Builder $q) => $q->where('direction', MessageDirection::Incoming),
                'messages as outgoing_messages_count' => fn (Builder $q) => $q->where('direction', MessageDirection::Outgoing),
            ])
            ->withMin('messages as first_message_at', 'created_at')
            ->withMax('messages as last_message_created_at', 'created_at');

        foreach ($query->lazy(500) as $c) {
            /** @var Conversation $c */
            yield [
                $c->displayName(),
                (string) $c->username,
                $c->platform?->value ?? '',
                (string) ($c->metaAccount?->page_name ?? $c->metaAccount?->ownExternalId() ?? ''),
                (string) $c->email,
                (string) $c->phone,
                $c->leadStage()->label(),
                implode(', ', $c->tagList()),
                self::date($c->getAttribute('first_message_at')),
                self::date($c->getAttribute('last_message_created_at') ?? $c->last_message_at),
                (int) $c->getAttribute('incoming_messages_count'),
                (int) $c->getAttribute('outgoing_messages_count'),
                (int) $c->getAttribute('messages_count'),
            ];
        }
    }

    private function sort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->orderBy('last_message_at')->orderBy('id'),
            'name' => $query->orderByRaw('COALESCE(customer_name, first_name, username, external_user_id) asc')->orderBy('id'),
            'created' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'unread' => $query->orderByDesc('unread_count')->orderByDesc('last_message_at')->orderByDesc('id'),
            default => $query->orderByDesc('last_message_at')->orderByDesc('id'),
        };
    }

    /** "+92 300 123-4567" → "+923001234567" (same format as auto-captured numbers). */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return (str_starts_with(trim($phone), '+') ? '+' : '').$digits;
    }

    private static function date(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    /** Neutralize spreadsheet formula injection (keeps "+44…" phone numbers readable). */
    private static function csvSafe(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (preg_match('/^[=@\t\r]/', $value) || preg_match('/^[+\-](?![\d\s()\-]+$)/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
