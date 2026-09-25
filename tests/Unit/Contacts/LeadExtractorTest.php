<?php

namespace Tests\Unit\Contacts;

use App\Services\Contacts\LeadExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LeadExtractorTest extends TestCase
{
    public static function phones(): array
    {
        return [
            'international with spaces' => ['my number is +44 20 7946 0958 thanks', ['+442079460958']],
            'local with dash' => ['call me 0300-1234567', ['03001234567']],
            'plain mobile with hint' => ['whatsapp: 03001234567', ['03001234567']],
            'us parentheses' => ['(555) 123-4567', ['5551234567']],
            'plus and parentheses' => ['+1 (555) 123-4567', ['+15551234567']],
            '00 prefix' => ['0092 300 1234567', ['+923001234567']],
            'glued after colon' => ['Phone:+923001234567', ['+923001234567']],
            'short german' => ['+49 30 901820', ['+4930901820']],
            'after an order number' => ['order 12345678, my phone is 0300 1234567', ['03001234567']],
            'two numbers' => ['+92 300 1111222 or +92 321 3334445', ['+923001111222', '+923213334445']],
        ];
    }

    public static function notPhones(): array
    {
        return [
            'order hash' => ['order #123456789'],
            'order number words' => ['my order number is 1234567890'],
            'iso date' => ['meeting on 2026-09-26 at 10'],
            'dmy date' => ['date 26-09-2026'],
            'price with currency sign' => ['price $1234567890'],
            'amount with currency word' => ['it costs 12345678 rs'],
            'decimal amount' => ['total: 1,234,567.89'],
            'card number' => ['4111 1111 1111 1111'],
            'url digits' => ['visit https://x.com/p/123456789012 now'],
            'id number' => ['my id is 12345678901'],
            'too short' => ['phone: 555 1234'],
            'times' => ['10:30-11:45 tomorrow'],
            'unformatted 8 digits' => ['12345678'],
            'repeated digit' => ['call 0000000000'],
            'tracking' => ['Tracking: 9400 1000 0000 0000 0000 00'],
            'national id' => ['my cnic 35202-1234567-1'],
            'empty' => [''],
        ];
    }

    #[DataProvider('phones')]
    public function test_it_finds_phone_numbers(string $text, array $expected): void
    {
        $this->assertSame($expected, (new LeadExtractor)->phones($text));
    }

    #[DataProvider('notPhones')]
    public function test_it_ignores_non_phone_numbers(string $text): void
    {
        $this->assertSame([], (new LeadExtractor)->phones($text));
    }

    public function test_it_finds_and_normalizes_emails(): void
    {
        $x = new LeadExtractor;

        $this->assertSame(['john.doe@example.co.uk'], $x->emails('email me at John.Doe@Example.co.uk.'));
        $this->assertSame(['a+b@shop.io', 'x@y.com'], $x->emails('a+b@shop.io, x@y.com and again A+B@shop.io'));
        $this->assertSame([], $x->emails('follow @shop on insta, or user@localhost'));
        $this->assertSame([], $x->emails(null));
    }

    public function test_extract_returns_both(): void
    {
        $this->assertSame(
            ['emails' => ['sara@example.com'], 'phones' => ['+971501234567']],
            (new LeadExtractor)->extract('Sara here: sara@example.com / +971 50 123 4567'),
        );
    }
}
