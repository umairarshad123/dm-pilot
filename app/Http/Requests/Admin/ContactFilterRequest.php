<?php

namespace App\Http\Requests\Admin;

use App\Enums\LeadStage;
use App\Enums\Platform;
use App\Models\Conversation;
use App\Services\Contacts\ContactService;
use App\Support\CurrentPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Contacts list filters from the query string (also used by export + bulk "all matching").
 * Unknown / invalid values are ignored rather than rejected (they come from shareable URLs).
 *
 *   q, platform, lead_stage, tag, has_email (0|1), has_phone (0|1), unread (1), reachable (1), sort, page_id
 *
 * page_id only applies when the page switcher is on "All pages"; otherwise the selected page always wins.
 */
class ContactFilterRequest extends FormRequest
{
    public const FILTER_KEYS = ['q', 'platform', 'lead_stage', 'tag', 'has_email', 'has_phone', 'unread', 'reachable', 'sort', 'page_id'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [];
    }

    /** @return array<string, mixed> filters for ContactService::query() */
    public function filters(CurrentPage $currentPage): array
    {
        $bool = fn (string $key) => in_array($this->param($key), ['0', '1'], true) ? $this->param($key) : null;

        $pageId = $currentPage->id();

        if ($pageId === null && ctype_digit((string) $this->param('page_id'))) {
            $pageId = $currentPage->connectedPages()->contains('id', (int) $this->param('page_id')) ? (int) $this->param('page_id') : null;
        }

        return array_filter([
            'search' => mb_substr(trim((string) $this->param('q')), 0, 200) ?: null,
            'platform' => Platform::tryFrom((string) $this->param('platform'))?->value,
            'lead_stage' => LeadStage::tryFrom((string) $this->param('lead_stage'))?->value,
            'tag' => Conversation::normalizeTag((string) $this->param('tag')) ?: null,
            'has_email' => $bool('has_email'),
            'has_phone' => $bool('has_phone'),
            'unread' => $this->param('unread') === '1' ? '1' : null,
            'sort' => in_array($this->param('sort'), ContactService::SORTS, true) ? $this->param('sort') : 'recent',
            'page_id' => $pageId,
        ], fn ($v) => $v !== null);
    }

    /** Only contacts with an email or a phone ("Has email/phone" segment). */
    public function reachable(): bool
    {
        return $this->param('reachable') === '1';
    }

    /** Filtered + sorted query (page scope included). */
    public function contactQuery(ContactService $contacts, CurrentPage $currentPage, ?array $filters = null): Builder
    {
        $query = $contacts->query($filters ?? $this->filters($currentPage));
        $currentPage->scope($query);

        if ($this->reachable()) {
            $query->where(fn (Builder $q) => $q
                ->where(fn (Builder $e) => $e->whereNotNull('email')->where('email', '!=', ''))
                ->orWhere(fn (Builder $p) => $p->whereNotNull('phone')->where('phone', '!=', '')));
        }

        return $query;
    }

    /** Active filter params (for links / "all matching" bulk actions). */
    public function activeParams(): array
    {
        return array_filter(
            array_map(fn ($k) => $this->param($k), array_combine(self::FILTER_KEYS, self::FILTER_KEYS)),
            fn ($v) => $v !== null && $v !== '',
        );
    }

    /** Query string value, falling back to the body (bulk POSTs send the filters in `filters`). */
    private function param(string $key): ?string
    {
        $value = $this->query($key);
        $value ??= data_get($this->input('filters'), $key);

        return is_scalar($value) ? (string) $value : null;
    }
}
