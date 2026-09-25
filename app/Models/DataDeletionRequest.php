<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DataDeletionRequest extends Model
{
    use HasFactory;

    public const TYPE_DELETION = 'deletion';

    public const TYPE_DEAUTHORIZE = 'deauthorize';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_NO_DATA = 'no_data';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'confirmation_code', 'type', 'meta_user_id', 'status', 'conversations_deleted', 'messages_deleted',
        'webhook_events_deleted', 'accounts_affected', 'details', 'issued_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'conversations_deleted' => 'integer',
            'messages_deleted' => 'integer',
            'webhook_events_deleted' => 'integer',
            'accounts_affected' => 'integer',
            'details' => 'array',
            'issued_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** Alphanumeric, unguessable code shown to the user (Meta requires alphanumeric). */
    public static function newConfirmationCode(): string
    {
        do {
            $code = Str::upper(Str::random(20));
        } while (static::query()->where('confirmation_code', $code)->exists());

        return $code;
    }

    public function getRouteKeyName(): string
    {
        return 'confirmation_code';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'Completed: matching data has been deleted',
            self::STATUS_NO_DATA => 'Completed: no data linked to this request was found',
            self::STATUS_FAILED => 'Failed: we will process it manually',
            default => 'Received: deletion in progress',
        };
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_NO_DATA], true);
    }
}
