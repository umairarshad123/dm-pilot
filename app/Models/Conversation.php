<?php

namespace App\Models;

use App\Enums\LeadStage;
use App\Enums\Platform;
use App\Observers\ConversationObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One conversation = one contact (customer) of one connected Page / Instagram account.
 *
 * Contact profile columns: first_name, last_name, username (IG handle), profile_pic_url (temporary CDN URL),
 * profile_fetched_at, email, phone, lead_stage (LeadStage), tags (list<string>), notes, unread_count,
 * lead_captured_at. `customer_name` stays the display name (filled from the Meta profile when empty).
 */
#[ObservedBy([ConversationObserver::class])]
class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'meta_account_id', 'platform', 'external_user_id', 'customer_name', 'status',
        'bot_enabled', 'human_takeover', 'bot_paused_until', 'last_message_at',
        'last_customer_message_at', 'meta',
        // Contact profile
        'first_name', 'last_name', 'username', 'profile_pic_url', 'profile_fetched_at', 'email', 'phone',
        'lead_stage', 'tags', 'notes', 'unread_count', 'lead_captured_at',
    ];

    protected $attributes = [
        'unread_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'bot_enabled' => 'boolean',
            'human_takeover' => 'boolean',
            'bot_paused_until' => 'datetime',
            'last_message_at' => 'datetime',
            'last_customer_message_at' => 'datetime',
            'meta' => 'array',
            'lead_stage' => LeadStage::class,
            'tags' => 'array',
            'unread_count' => 'integer',
            'profile_fetched_at' => 'datetime',
            'lead_captured_at' => 'datetime',
        ];
    }

    public function metaAccount(): BelongsTo
    {
        return $this->belongsTo(MetaAccount::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** Whether the bot may auto-reply in this conversation right now (conversation-level rules only). */
    public function botCanReply(): bool
    {
        return $this->bot_enabled
            && ! $this->human_takeover
            && $this->status === 'open'
            && ! $this->isPaused();
    }

    public function isPaused(): bool
    {
        return $this->bot_paused_until !== null && $this->bot_paused_until->isFuture();
    }

    public function pauseBotFor(int $minutes): void
    {
        $until = Carbon::now()->addMinutes($minutes);

        // Never shorten an existing longer pause.
        if (! $this->isPaused() || $this->bot_paused_until->lt($until)) {
            $this->forceFill(['bot_paused_until' => $until])->save();
        }
    }

    /** Meta standard messaging window: replies allowed within 24h of the customer's last message. */
    public function withinMessagingWindow(): bool
    {
        return $this->last_customer_message_at !== null
            && $this->last_customer_message_at->gt(Carbon::now()->subHours(24));
    }

    // ---------------------------------------------------------------------------------------------
    // Contact helpers
    // ---------------------------------------------------------------------------------------------

    /** Best available human-readable name: customer_name → first+last → @username → "Facebook user …1234". */
    public function displayName(): string
    {
        $full = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return match (true) {
            filled($this->customer_name) => (string) $this->customer_name,
            $full !== '' => $full,
            filled($this->username) => '@'.$this->username,
            default => sprintf('%s user …%s',
                $this->platform === Platform::Instagram ? 'Instagram' : 'Facebook',
                substr((string) $this->external_user_id, -4)),
        };
    }

    /** One or two uppercase initials for avatar fallbacks ("Jane Doe" → "JD", "@shop" → "S", unknown → "?"). */
    public function initials(): string
    {
        if (blank($this->customer_name) && blank($this->first_name) && blank($this->last_name) && blank($this->username)) {
            return '?';
        }

        $words = preg_split('/[\s._\-]+/u', ltrim($this->displayName(), '@'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = array_map(fn (string $w) => mb_substr($w, 0, 1), array_slice($words, 0, 2));
        $initials = mb_strtoupper(implode('', $letters));

        return $initials !== '' ? $initials : '?';
    }

    /** Stage with NULL treated as New. */
    public function leadStage(): LeadStage
    {
        return $this->lead_stage ?? LeadStage::New;
    }

    /** Normalize a tag: trimmed, single-spaced, lowercase, max 50 chars. Empty string = invalid. */
    public static function normalizeTag(string $tag): string
    {
        $tag = preg_replace('/\s+/u', ' ', trim($tag)) ?? '';

        return mb_substr(mb_strtolower($tag), 0, 50);
    }

    /**
     * @param  iterable<string>  $tags
     * @return list<string> normalized, unique, empties removed (input order kept)
     */
    public static function normalizeTags(iterable $tags): array
    {
        $out = [];

        foreach ($tags as $tag) {
            $tag = self::normalizeTag((string) $tag);

            if ($tag !== '' && ! in_array($tag, $out, true)) {
                $out[] = $tag;
            }
        }

        return $out;
    }

    /** @return list<string> */
    public function tagList(): array
    {
        return array_values((array) ($this->tags ?? []));
    }

    public function hasTag(string $tag): bool
    {
        return in_array(self::normalizeTag($tag), $this->tagList(), true);
    }

    /** Add one or more tags and save. */
    public function addTag(string ...$tags): static
    {
        return $this->syncTags([...$this->tagList(), ...$tags]);
    }

    /** Remove one or more tags and save. */
    public function removeTag(string ...$tags): static
    {
        $remove = self::normalizeTags($tags);

        return $this->syncTags(array_filter($this->tagList(), fn ($t) => ! in_array($t, $remove, true)));
    }

    /** Replace all tags (normalized) and save. */
    public function syncTags(iterable $tags): static
    {
        $tags = self::normalizeTags($tags);
        $this->forceFill(['tags' => $tags === [] ? null : $tags])->save();

        return $this;
    }

    /** Reset the unread counter (admin opened the conversation). */
    public function markRead(): static
    {
        if ((int) $this->unread_count !== 0) {
            $this->forceFill(['unread_count' => 0])->save();
        }

        return $this;
    }

    // ---------------------------------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------------------------------

    /** Free-text search on names, username, email, phone (also digits-only) and the PSID/IGSID. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        // LIKE wildcards in the term are not escaped (no portable ESCAPE default across MySQL/SQLite); harmless here.
        $like = '%'.ltrim($term, '@').'%';
        $digits = preg_replace('/\D+/', '', $term) ?? '';

        return $query->where(function (Builder $q) use ($like, $digits, $term) {
            foreach (['customer_name', 'first_name', 'last_name', 'username', 'email', 'phone', 'external_user_id'] as $column) {
                $q->orWhere($column, 'like', $like);
            }

            if (strlen($digits) >= 4 && $digits !== $term) {
                $q->orWhere('phone', 'like', '%'.$digits.'%');
            }

            // Local "0300…" typed, international "+92300…" stored.
            if (str_starts_with($digits, '0') && strlen(ltrim($digits, '0')) >= 7) {
                $q->orWhere('phone', 'like', '%'.ltrim($digits, '0').'%');
            }

            // "Jane Doe" → first_name + last_name
            $parts = preg_split('/\s+/', $term, 2);

            if (count($parts) === 2) {
                $q->orWhere(fn (Builder $n) => $n->where('first_name', 'like', $parts[0].'%')->where('last_name', 'like', $parts[1].'%'));
            }
        });
    }

    /** Limit to one connected account (Page / IG account); null = all. (Not named "forPage": that would shadow the query builder's pagination method.) */
    public function scopeForAccount(Builder $query, ?int $metaAccountId): Builder
    {
        return $metaAccountId === null ? $query : $query->where('meta_account_id', $metaAccountId);
    }

    public function scopeWithTag(Builder $query, ?string $tag): Builder
    {
        $tag = self::normalizeTag((string) $tag);

        return $tag === '' ? $query : $query->whereJsonContains('tags', $tag);
    }

    /** Filter by stage; "new" also matches NULL (legacy rows). */
    public function scopeLeadStage(Builder $query, LeadStage|string|null $stage): Builder
    {
        $stage = is_string($stage) ? LeadStage::tryFrom($stage) : $stage;

        if ($stage === null) {
            return $query;
        }

        return $stage === LeadStage::New
            ? $query->where(fn (Builder $q) => $q->whereNull('lead_stage')->orWhere('lead_stage', LeadStage::New->value))
            : $query->where('lead_stage', $stage->value);
    }

    /** Short excerpt helper used by lists. */
    public static function excerpt(?string $text, int $limit = 120): string
    {
        return Str::limit(preg_replace('/\s+/u', ' ', trim((string) $text)) ?? '', $limit);
    }
}
