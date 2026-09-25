<?php

namespace Tests\Feature\UI\Settings;

use App\Models\DataDeletionRequest;
use App\Models\MetaAccount;
use App\Models\User;
use App\Models\WebhookEvent;
use Tests\Feature\Admin\AdminTestCase;

class SettingsPageTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'meta.app_id' => '1829538868411022',
            'meta.app_secret' => 'super-secret-app-secret',
            'meta.verify_token' => 'verify-me-123',
            'meta.graph_version' => 'v26.0',
            'openai.api_key' => 'sk-test-openai-key-123',
            'anthropic.api_key' => null,
            'ai.provider' => 'openai',
        ]);
    }

    public function test_connection_tab_is_default(): void
    {
        $this->get(route('admin.settings.index'))->assertOk()
            ->assertSee(url('/webhooks/meta'))
            ->assertSee('verify-me-123') // behind "Reveal" (admins only)
            ->assertSee('1829538868411022')
            ->assertSee('v26.0')
            ->assertSee('Set (hidden)')
            ->assertSee(route('admin.meta-accounts.oauth.callback'))
            ->assertDontSee('super-secret-app-secret');
    }

    public function test_ai_tab_shows_provider_status_without_keys(): void
    {
        $this->get(route('admin.settings.index', ['tab' => 'ai']))->assertOk()
            ->assertSee('OpenAI')->assertSee('Claude')
            ->assertSee('Configured')->assertSee('No API key')
            ->assertSee('Default')
            ->assertSee('ANTHROPIC_API_KEY')
            ->assertDontSee('sk-test-openai-key-123');
    }

    public function test_health_tab_lists_checks(): void
    {
        MetaAccount::factory()->create();
        WebhookEvent::create(['object' => 'page', 'payload_hash' => str_repeat('c', 64), 'payload' => [], 'status' => 'failed']);

        $this->get(route('admin.settings.index', ['tab' => 'health']))->assertOk()
            ->assertSee('App secret')->assertSee('Verify token')->assertSee('Queue connection')
            ->assertSee('Failed jobs')->assertSee('Last webhook received')->assertSee('Failed events (24h)')
            ->assertSee('schedule:run')
            ->assertViewHas('summary', fn ($s) => $s['total'] > 5 && $s['warnings'] >= 1);
    }

    public function test_privacy_tab_lists_retention_links_and_requests(): void
    {
        DataDeletionRequest::create([
            'confirmation_code' => 'ABCDEF1234567890ABCD', 'type' => DataDeletionRequest::TYPE_DEAUTHORIZE,
            'meta_user_id' => '123', 'status' => DataDeletionRequest::STATUS_COMPLETED, 'accounts_affected' => 2,
        ]);

        $this->get(route('admin.settings.index', ['tab' => 'privacy']))->assertOk()
            ->assertSee(config('legal.retention.conversations_months').' months')
            ->assertSee(url('/privacy'))->assertSee(url('/terms'))->assertSee(url('/data-deletion'))
            ->assertSee('ABCDEF1234567890ABCD')->assertSee('Deauthorize')->assertSee('2 paused');
    }

    public function test_app_review_tab_lists_exact_urls(): void
    {
        $this->get(route('admin.settings.index', ['tab' => 'app-review']))->assertOk()
            ->assertSee(url('/meta/data-deletion'))
            ->assertSee(url('/meta/deauthorize'))
            ->assertSee(route('admin.meta-accounts.oauth.callback'))
            ->assertSee('pages_messaging')
            ->assertSee('Checklist');
    }

    public function test_unknown_tab_falls_back_to_connection(): void
    {
        $this->get(route('admin.settings.index', ['tab' => '../../etc']))->assertOk()->assertViewHas('tab', 'connection');
    }

    public function test_tabs_link_to_webhook_events(): void
    {
        $this->get(route('admin.settings.index'))->assertSee(route('admin.webhook-events.index'))->assertSee('App Review');
    }

    public function test_access_control(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.settings.index'))->assertForbidden();
        auth()->logout();
        $this->get(route('admin.settings.index'))->assertRedirect(route('login'));
    }
}
