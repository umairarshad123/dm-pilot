<?php

namespace App\Models;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Observers\MessageObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ObservedBy([MessageObserver::class])]
class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id', 'external_message_id', 'direction', 'sender_type', 'body',
        'attachments', 'payload', 'status', 'error', 'in_reply_to_id', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'sender_type' => SenderType::class,
            'status' => MessageStatus::class,
            'attachments' => 'array',
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function inReplyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'in_reply_to_id');
    }

    public function reply(): HasOne
    {
        return $this->hasOne(Message::class, 'in_reply_to_id');
    }

    /** Incoming message written by the customer. */
    public function isFromCustomer(): bool
    {
        return $this->direction === MessageDirection::Incoming && $this->sender_type === SenderType::Customer;
    }

    /** Where an outgoing message came from, when marked by the sender (e.g. "automation", "ai", "fallback"). */
    public function sourceMarker(): ?string
    {
        $source = $this->payload['source'] ?? null;

        return is_string($source) ? $source : null;
    }
}
