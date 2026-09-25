<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotSetting extends Model
{
    protected $fillable = [
        'meta_account_id', 'bot_enabled', 'system_prompt', 'business_info', 'faqs', 'offers',
        'channel_instructions', 'model', 'temperature', 'max_output_tokens', 'history_limit',
        'reply_delay_seconds', 'human_takeover_minutes', 'fallback_message', 'ai_provider',
    ];

    protected function casts(): array
    {
        return [
            'bot_enabled' => 'boolean',
            'faqs' => 'array',
            'channel_instructions' => 'array',
            'temperature' => 'float',
            'max_output_tokens' => 'integer',
            'history_limit' => 'integer',
            'reply_delay_seconds' => 'integer',
            'human_takeover_minutes' => 'integer',
        ];
    }

    public function metaAccount(): BelongsTo
    {
        return $this->belongsTo(MetaAccount::class);
    }

    public static function global(): self
    {
        return static::firstOrCreate(['meta_account_id' => null]);
    }
}
