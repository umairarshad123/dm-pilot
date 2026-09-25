<?php

namespace App\Enums;

enum Platform: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';

    /** Map the webhook `object` field to a platform. */
    public static function fromWebhookObject(?string $object): ?self
    {
        return match ($object) {
            'page' => self::Facebook,
            'instagram' => self::Instagram,
            default => null,
        };
    }
}
