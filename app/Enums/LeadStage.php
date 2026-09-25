<?php

namespace App\Enums;

/**
 * Contact pipeline stage (conversations.lead_stage). NULL in the DB is treated as New.
 *
 * Automatic transitions: new conversation → New; email/phone captured from a customer message while
 * New → Qualified (the contact handed over reachable details). Everything else is set manually.
 */
enum LeadStage: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Customer = 'customer';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Qualified => 'Qualified',
            self::Customer => 'Customer',
            self::Lost => 'Lost',
        };
    }

    /** Semantic color name for badges (map to design-system tokens in the UI). */
    public function color(): string
    {
        return match ($this) {
            self::New => 'gray',
            self::Contacted => 'blue',
            self::Qualified => 'amber',
            self::Customer => 'green',
            self::Lost => 'red',
        };
    }

    /** Hex fallback for charts / places without the design tokens. */
    public function hex(): string
    {
        return match ($this) {
            self::New => '#6b7280',
            self::Contacted => '#3b82f6',
            self::Qualified => '#f59e0b',
            self::Customer => '#10b981',
            self::Lost => '#ef4444',
        };
    }

    /** @return array<string, string> value => label, for selects */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
