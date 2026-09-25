<?php

namespace App\Services\AI;

use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\Prompt\PromptContext;
use App\Services\AI\Prompt\PromptSection;
use App\Services\AI\Prompt\Sections\BusinessInfoSection;
use App\Services\AI\Prompt\Sections\ChannelSection;
use App\Services\AI\Prompt\Sections\FaqSection;
use App\Services\AI\Prompt\Sections\OffersSection;
use App\Services\AI\Prompt\Sections\ReplyStyleSection;
use App\Services\AI\Prompt\Sections\SystemPromptSection;

/** Builds the OpenAI `instructions` (from ordered sections) and the conversation `input`. */
class PromptBuilder
{
    public const DEFAULT_SECTIONS = [
        SystemPromptSection::class,
        BusinessInfoSection::class,
        FaqSection::class,
        OffersSection::class,
        ChannelSection::class,
        ReplyStyleSection::class,
    ];

    private const MAX_MESSAGE_CHARS = 4000;

    /** @param  list<PromptSection>  $sections */
    public function __construct(private readonly array $sections) {}

    public function instructions(PromptContext $context): string
    {
        $blocks = [];

        foreach ($this->sections as $section) {
            $text = $section->render($context);

            if ($text !== null && trim($text) !== '') {
                $blocks[] = trim($text);
            }
        }

        return implode("\n\n", $blocks);
    }

    /**
     * Last $limit delivered messages as Responses API input items (oldest first).
     *
     * @param  list<int>  $excludeIds
     * @return list<array{role: string, content: string}>
     */
    public function history(Conversation $conversation, int $limit, array $excludeIds = []): array
    {
        return $conversation->messages()
            ->whereIn('status', [MessageStatus::Received, MessageStatus::Sent])
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn (Message $message) => [
                'role' => $message->sender_type === SenderType::Customer ? 'user' : 'assistant',
                'content' => $this->render($message),
            ])
            ->filter(fn (array $item) => $item['content'] !== '')
            ->values()
            ->all();
    }

    /** Text for one message, with attachments described (e.g. "[customer sent an image]"). */
    public function render(Message $message): string
    {
        $who = $message->sender_type === SenderType::Customer ? 'customer' : 'we';
        $parts = [];

        if (filled($message->body)) {
            $parts[] = mb_substr(trim((string) $message->body), 0, self::MAX_MESSAGE_CHARS);
        }

        foreach ((array) $message->attachments as $attachment) {
            $parts[] = sprintf('[%s sent %s]', $who, $this->describe((string) ($attachment['type'] ?? 'file')));
        }

        return implode(' ', $parts);
    }

    private function describe(string $type): string
    {
        return match ($type) {
            'image' => 'an image',
            'video', 'ig_reel', 'reel' => 'a video',
            'audio' => 'a voice message',
            'file' => 'a file',
            'sticker', 'like_heart' => 'a sticker',
            'location' => 'a location',
            'share', 'template', 'fallback', 'ig_post' => 'a shared post/link',
            'story_mention' => 'a story mention',
            default => 'an attachment ('.preg_replace('/[^a-z_]/', '', strtolower($type)).')',
        };
    }
}
