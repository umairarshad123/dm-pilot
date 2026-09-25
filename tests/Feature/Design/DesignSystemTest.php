<?php

namespace Tests\Feature\Design;

use App\Models\MetaAccount;
use App\Models\User;
use App\Support\CurrentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DesignSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['name' => 'Ada Admin']);
        $this->admin->forceFill(['is_admin' => true])->save();
    }

    /** Every sidebar destination + the style guide. */
    public static function navRoutes(): array
    {
        return [
            'dashboard' => ['admin.dashboard'],
            'live chat' => ['admin.conversations.index'],
            'contacts' => ['admin.contacts.index'],
            'bot studio' => ['admin.bot-settings.edit'],
            'automations' => ['admin.automations.index'],
            'pages & channels' => ['admin.meta-accounts.index'],
            'connect a page' => ['admin.meta-accounts.connect'],
            'settings' => ['admin.settings.index'],
            'webhook events' => ['admin.webhook-events.index'],
            'style guide' => ['admin.ui'],
        ];
    }

    #[DataProvider('navRoutes')]
    public function test_admin_nav_route_renders_inside_the_app_shell(string $name): void
    {
        MetaAccount::factory()->create(['page_name' => 'Acme Store']);
        MetaAccount::factory()->instagram()->create(['page_name' => 'Acme IG']);

        $this->actingAs($this->admin)
            ->get(route($name))
            ->assertOk()
            ->assertSee('id="sidebar"', false)
            ->assertSee('DM Pilot')
            ->assertSee('Live Chat')
            ->assertSee('Pages &amp; Channels', false)
            ->assertSee(route('admin.page-switch'), false)
            ->assertSee('Acme IG')
            ->assertSee('Connect a page')
            ->assertSee('Log out');
    }

    #[DataProvider('navRoutes')]
    public function test_nav_routes_are_admin_only(string $name): void
    {
        $this->get(route($name))->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get(route($name))->assertForbidden();
    }

    public function test_active_nav_item_is_marked(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.contacts.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('admin.contacts.index'), '#').'"\s+aria-current="page"#', $html);
    }

    public function test_style_guide_renders_every_component(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/_ui')->assertOk();

        foreach (['Buttons', 'Badges &amp; channels', 'Avatars', 'Cards &amp; stats', 'Forms', 'Tabs', 'Modal &amp; dropdown', 'Alerts &amp; toasts', 'Tables &amp; filters', 'Icons'] as $section) {
            $response->assertSee($section, false);
        }

        // Code snippets are shown escaped, not rendered.
        $response->assertSee('&lt;x-ui.button variant=&quot;primary&quot; icon=&quot;plus&quot;&gt;New&lt;/x-ui.button&gt;', false);
        $response->assertSee('role="switch"', false);
        $response->assertSee('Messenger')->assertSee('Instagram');
    }

    public function test_page_switch_stores_selection_in_session(): void
    {
        $page = MetaAccount::factory()->create(['page_name' => 'Acme Store']);

        $this->actingAs($this->admin)
            ->from(route('admin.conversations.index'))
            ->post(route('admin.page-switch'), ['meta_account_id' => $page->id])
            ->assertRedirect(route('admin.conversations.index'))
            ->assertSessionHas(CurrentPage::SESSION_KEY, $page->id)
            ->assertSessionHas('success');

        $this->assertSame($page->id, app(CurrentPage::class)->id());
        $this->assertSame('Acme Store', app(CurrentPage::class)->label());
    }

    public function test_page_switch_to_all_pages_clears_selection(): void
    {
        $page = MetaAccount::factory()->create();

        $this->actingAs($this->admin)
            ->withSession([CurrentPage::SESSION_KEY => $page->id])
            ->post(route('admin.page-switch'), ['meta_account_id' => ''])
            ->assertRedirect()
            ->assertSessionMissing(CurrentPage::SESSION_KEY);

        $this->assertTrue(app(CurrentPage::class)->isAll());
        $this->assertSame('All pages', app(CurrentPage::class)->label());
    }

    public function test_page_switch_rejects_unknown_account(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.dashboard'))
            ->post(route('admin.page-switch'), ['meta_account_id' => 999])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHasErrors('meta_account_id')
            ->assertSessionMissing(CurrentPage::SESSION_KEY);
    }

    public function test_page_switch_requires_admin(): void
    {
        $page = MetaAccount::factory()->create();

        $this->post(route('admin.page-switch'), ['meta_account_id' => $page->id])->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->post(route('admin.page-switch'), ['meta_account_id' => $page->id])->assertForbidden();
    }

    public function test_selected_page_is_shown_in_the_shell(): void
    {
        $page = MetaAccount::factory()->instagram()->create(['page_name' => 'Zeta Boutique']);

        $this->actingAs($this->admin)
            ->withSession([CurrentPage::SESSION_KEY => $page->id])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('currentPage', fn (CurrentPage $cp) => $cp->id() === $page->id)
            ->assertSee('Zeta Boutique')
            ->assertSee('Instagram · Active');
    }

    public function test_deleted_selected_page_falls_back_to_all_pages(): void
    {
        $page = MetaAccount::factory()->create();
        $id = $page->id;
        $page->delete();

        $this->actingAs($this->admin)
            ->withSession([CurrentPage::SESSION_KEY => $id])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSessionMissing(CurrentPage::SESSION_KEY)
            ->assertSee('All pages');
    }

    public function test_current_page_scope_filters_queries(): void
    {
        $a = MetaAccount::factory()->create();
        MetaAccount::factory()->create();
        $current = app(CurrentPage::class);

        $this->assertSame(2, $current->scope(MetaAccount::query(), 'id')->count());

        $current->set($a->id);

        $this->assertSame([$a->id], $current->scope(MetaAccount::query(), 'id')->pluck('id')->all());
    }

    public function test_flash_messages_render_as_toasts(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['success' => 'Settings saved nicely', 'error' => 'Something broke'])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Settings saved nicely')
            ->assertSee('Something broke')
            ->assertSee('role="alert"', false);
    }

    public function test_login_page_uses_the_new_design(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Log in')
            ->assertSee('Welcome back')
            ->assertSee('DM Pilot')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false);
    }

    public function test_legacy_layout_delegates_to_the_app_shell(): void
    {
        $this->actingAs($this->admin);

        $html = Blade::render(<<<'BLADE'
            @extends('layouts.admin')
            @section('title', 'Legacy page')
            @section('content')<div class="card">Hello legacy</div>@endsection
            @push('scripts')<script>window.legacyPushed = true;</script>@endpush
            BLADE);

        $this->assertStringContainsString('<div class="legacy"><div class="card">Hello legacy</div>', $html);
        $this->assertStringContainsString('id="sidebar"', $html);
        $this->assertStringContainsString('<title>Legacy page · DM Pilot</title>', $html);
        $this->assertStringContainsString('window.legacyPushed = true;', $html);
    }
}
