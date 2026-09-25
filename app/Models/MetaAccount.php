<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MetaAccount extends Model
{
    use HasFactory;

    public const AUTH_FACEBOOK_LOGIN = 'facebook_login';

    public const AUTH_INSTAGRAM_LOGIN = 'instagram_login';

    protected $fillable = [
        'platform', 'auth_type', 'page_id', 'instagram_account_id', 'page_name',
        'access_token', 'token_expires_at', 'token_checked_at', 'active', 'settings',
    ];

    // Never serialize the token (admin JSON, logs, queue payload dumps).
    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'token_checked_at' => 'datetime',
            'active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function botSetting(): HasOne
    {
        return $this->hasOne(BotSetting::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * The ID Meta uses for this account in webhooks (`entry[].id` / `recipient.id`):
     * Page ID for Messenger, Instagram professional account ID for Instagram.
     */
    public function ownExternalId(): ?string
    {
        return $this->platform === Platform::Instagram ? $this->instagram_account_id : $this->page_id;
    }

    /** Find the active account that owns a webhook entry id for a platform. */
    public static function findForWebhook(Platform $platform, string $entryId): ?self
    {
        $column = $platform === Platform::Instagram ? 'instagram_account_id' : 'page_id';

        return static::query()->active()->where('platform', $platform)->where($column, $entryId)->first();
    }

    /** Token preview safe for logs/UI, e.g. "EAAZ…Ruyw". */
    public function maskedToken(): string
    {
        $token = (string) $this->access_token;

        return strlen($token) > 12 ? substr($token, 0, 4).'…'.substr($token, -4) : '****';
    }

    /** One key of the `settings` JSON (dot notation allowed). */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get((array) $this->settings, $key, $default);
    }

    /** Set one top-level key of the `settings` JSON and save. Null removes the key. */
    public function putSetting(string $key, mixed $value): static
    {
        $settings = (array) $this->settings;

        if ($value === null) {
            unset($settings[$key]);
        } else {
            $settings[$key] = $value;
        }

        $this->forceFill(['settings' => $settings ?: null])->save();

        return $this;
    }

    /**
     * Last Messenger profile config saved through MetaMessagingService::setMessengerProfile().
     *
     * @return array{greeting?: ?string, get_started?: bool, ice_breakers?: list<array{question: string, payload: string}>, updated_at?: string}
     */
    public function messengerProfile(): array
    {
        return (array) $this->setting('messenger_profile', []);
    }

    public function saveMessengerProfile(array $profile): static
    {
        return $this->putSetting('messenger_profile', $profile);
    }
}
