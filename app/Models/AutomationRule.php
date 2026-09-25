<?php

namespace App\Models;

use App\Services\Automation\KeywordMatcher;
use App\Services\Automation\ValidAutomationKeywords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * A keyword or welcome auto-reply ("Keywords" / "Welcome message" in ManyChat terms).
 * When a rule matches an incoming message, its reply_text IS the bot reply (the AI is not called).
 *
 * @property list<string>|null $keywords
 */
class AutomationRule extends Model
{
    use HasFactory;

    public const TRIGGER_KEYWORD = 'keyword';

    public const TRIGGER_WELCOME = 'welcome';

    public const TRIGGERS = [self::TRIGGER_KEYWORD, self::TRIGGER_WELCOME];

    public const MATCH_EXACT = 'exact';

    public const MATCH_CONTAINS = 'contains';

    public const MATCH_STARTS_WITH = 'starts_with';

    public const MATCH_REGEX = 'regex';

    public const MATCH_TYPES = [self::MATCH_EXACT, self::MATCH_CONTAINS, self::MATCH_STARTS_WITH, self::MATCH_REGEX];

    /** Placeholders replaced in reply_text. */
    public const VARIABLES = ['{first_name}', '{name}', '{page_name}'];

    protected $fillable = [
        'meta_account_id', 'name', 'trigger', 'match_type', 'keywords', 'reply_text', 'priority', 'active',
    ];

    protected $attributes = [
        'trigger' => self::TRIGGER_KEYWORD,
        'match_type' => self::MATCH_CONTAINS,
        'priority' => 0,
        'active' => true,
        'trigger_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'priority' => 'integer',
            'active' => 'boolean',
            'trigger_count' => 'integer',
            'last_triggered_at' => 'datetime',
        ];
    }

    /**
     * Recommended validation rules for create/update forms (keywords as an array of strings).
     *
     * @return array<string, mixed>
     */
    public static function validationRules(): array
    {
        return [
            'meta_account_id' => ['nullable', 'integer', 'exists:meta_accounts,id'],
            'name' => ['required', 'string', 'max:120'],
            'trigger' => ['required', Rule::in(self::TRIGGERS)],
            'match_type' => ['required_if:trigger,'.self::TRIGGER_KEYWORD, 'nullable', Rule::in(self::MATCH_TYPES)],
            'keywords' => ['required_if:trigger,'.self::TRIGGER_KEYWORD, 'nullable', 'array', 'max:50', new ValidAutomationKeywords],
            'keywords.*' => ['string', 'max:'.KeywordMatcher::MAX_KEYWORD_LENGTH],
            'reply_text' => ['required', 'string', 'max:2000'],
            'priority' => ['nullable', 'integer', 'between:-1000,1000'],
            'active' => ['boolean'],
        ];
    }

    public function metaAccount(): BelongsTo
    {
        return $this->belongsTo(MetaAccount::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /** Rules that apply to an account: its own + global (null account = global only). */
    public function scopeApplicableTo(Builder $query, ?MetaAccount $account): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('meta_account_id')
            ->when($account, fn (Builder $q) => $q->orWhere('meta_account_id', $account->id)));
    }

    /** Page-specific first, then priority desc, then oldest first. */
    public function scopeInMatchOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN meta_account_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('priority')
            ->orderBy('id');
    }

    /** Trimmed, lower-cased, de-duplicated non-empty keywords. @return list<string> */
    public function normalizedKeywords(): array
    {
        return KeywordMatcher::normalizeKeywords((array) $this->keywords, $this->match_type === self::MATCH_REGEX);
    }

    public function isGlobal(): bool
    {
        return $this->meta_account_id === null;
    }

    /** Atomically bump trigger_count + last_triggered_at (safe with concurrent workers). */
    public function recordTrigger(): void
    {
        $now = Carbon::now();

        static::query()->whereKey($this->getKey())->update([
            'trigger_count' => $this->getConnection()->raw('trigger_count + 1'),
            'last_triggered_at' => $now,
        ]);

        $this->forceFill(['trigger_count' => $this->trigger_count + 1, 'last_triggered_at' => $now])->syncOriginal();
    }
}
