<?php

namespace App\Services\Bot;

final readonly class ReplyDecision
{
    private function __construct(public bool $shouldReply, public string $reason) {}

    public static function reply(): self
    {
        return new self(true, 'ok');
    }

    public static function skip(string $reason): self
    {
        return new self(false, $reason);
    }
}
