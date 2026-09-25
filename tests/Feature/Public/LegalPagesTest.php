<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config([
            'legal.operator_name' => 'Apex Growth Solutions',
            'legal.contact_email' => 'privacy@apex.test',
        ]);
    }

    /** @return array<string, array{string, list<string>}> */
    public static function pages(): array
    {
        return [
            'privacy' => ['/privacy', [
                'Privacy Policy', 'Effective date', 'Information we receive', 'PSID', 'IGSID', 'Attachments',
                'How we use information', 'OpenAI', 'Anthropic', 'Retention', '12 months', '30 days', 'Security',
                'encrypted', 'HTTPS', 'Your rights', 'How to delete your data',
            ]],
            'terms' => ['/terms', ['Terms of Service', 'Acceptable use', 'Meta Platform Terms', 'Limitation of liability']],
            'data deletion' => ['/data-deletion', ['Data Deletion Instructions', 'Delete my data', 'Apps and websites', 'confirmation code']],
            'about' => ['/about', ['Custom Bot Integration', 'pages_messaging', 'instagram_manage_messages', 'Human handover']],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_renders_with_key_sections(string $uri, array $needles): void
    {
        $response = $this->get($uri)->assertOk();

        foreach ($needles as $needle) {
            $response->assertSee($needle, false);
        }

        $response->assertSee('Apex Growth Solutions')
            ->assertSee('privacy@apex.test')
            ->assertSee(route('public.privacy'), false)
            ->assertSee(route('public.terms'), false)
            ->assertSee(route('public.data-deletion'), false);
    }

    public function test_pages_are_public_and_do_not_redirect_to_login(): void
    {
        foreach (['/privacy', '/terms', '/data-deletion', '/about'] as $uri) {
            $this->get($uri)->assertOk()->assertDontSee('name="password"', false);
        }
    }
}
