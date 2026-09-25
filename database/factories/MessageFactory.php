<?php

namespace Database\Factories;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Message> */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'external_message_id' => 'm_'.fake()->unique()->bothify('????????????????????'),
            'direction' => MessageDirection::Incoming,
            'sender_type' => SenderType::Customer,
            'body' => fake()->sentence(),
            'status' => MessageStatus::Received,
            'sent_at' => now(),
        ];
    }

    public function fromBot(): static
    {
        return $this->state(fn () => [
            'direction' => MessageDirection::Outgoing,
            'sender_type' => SenderType::Bot,
            'status' => MessageStatus::Sent,
        ]);
    }

    public function fromHuman(): static
    {
        return $this->state(fn () => [
            'direction' => MessageDirection::Outgoing,
            'sender_type' => SenderType::Human,
            'status' => MessageStatus::Sent,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => MessageStatus::Failed, 'error' => 'Test failure']);
    }
}
