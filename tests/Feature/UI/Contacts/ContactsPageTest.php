<?php

namespace Tests\Feature\UI\Contacts;

use App\Enums\LeadStage;
use App\Jobs\FetchContactProfile;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetaAccount;
use App\Models\User;
use App\Support\CurrentPage;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Admin\AdminTestCase;

class ContactsPageTest extends AdminTestCase
{
    private MetaAccount $page;

    private MetaAccount $otherPage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->page = MetaAccount::factory()->create(['page_name' => 'Acme Store']);
        $this->otherPage = MetaAccount::factory()->instagram()->create(['page_name' => 'Acme IG']);
    }

    private function contact(array $attrs = [], ?MetaAccount $page = null): Conversation
    {
        return Conversation::factory()->create(['meta_account_id' => ($page ?? $this->page)->id] + $attrs);
    }

    public function test_guests_are_redirected_and_non_admins_forbidden(): void
    {
        auth()->logout();
        $this->get(route('admin.contacts.index'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('admin.contacts.index'))->assertForbidden();
        $this->actingAs($user)->postJson(route('admin.contacts.bulk'), ['action' => 'delete', 'ids' => [1], 'confirm' => 'DELETE'])->assertForbidden();
    }

    public function test_empty_state_when_there_are_no_contacts(): void
    {
        $this->get(route('admin.contacts.index'))
            ->assertOk()
            ->assertSee('No contacts yet')
            ->assertSee('Open Live Chat');
    }

    public function test_list_renders_contacts_with_stage_tags_and_bot_state(): void
    {
        $this->contact(['customer_name' => 'Jane Doe', 'username' => 'janed', 'email' => 'jane@example.com', 'tags' => ['vip', 'wholesale', 'repeat'], 'lead_stage' => LeadStage::Customer, 'human_takeover' => true]);
        $this->contact(['customer_name' => 'Bob Paused', 'bot_paused_until' => now()->addHour()]);

        $this->get(route('admin.contacts.index'))
            ->assertOk()
            ->assertSee('Jane Doe')
            ->assertSee('@janed')
            ->assertSee('jane@example.com')
            ->assertSee('Customer')
            ->assertSee('vip')
            ->assertSee('+1')
            ->assertSee('Human')
            ->assertSee('Paused')
            ->assertSee('Has email/phone')
            ->assertSee(route('admin.conversations.show', Conversation::where('customer_name', 'Jane Doe')->first()), false);
    }

    public function test_filters_and_segments(): void
    {
        $this->contact(['customer_name' => 'Alice New', 'lead_stage' => LeadStage::New]);
        $this->contact(['customer_name' => 'Quinn Qualified', 'lead_stage' => LeadStage::Qualified, 'phone' => '+923001234567']);
        $this->contact(['customer_name' => 'Cara Customer', 'lead_stage' => LeadStage::Customer, 'email' => 'cara@example.com', 'unread_count' => 3, 'tags' => ['vip']]);

        $this->get(route('admin.contacts.index', ['lead_stage' => 'qualified']))
            ->assertSee('Quinn Qualified')->assertDontSee('Alice New')->assertDontSee('Cara Customer');

        $this->get(route('admin.contacts.index', ['reachable' => '1']))
            ->assertSee('Quinn Qualified')->assertSee('Cara Customer')->assertDontSee('Alice New');

        $this->get(route('admin.contacts.index', ['unread' => '1']))
            ->assertSee('Cara Customer')->assertDontSee('Quinn Qualified');

        $this->get(route('admin.contacts.index', ['tag' => 'VIP']))
            ->assertSee('Cara Customer')->assertDontSee('Alice New');

        $this->get(route('admin.contacts.index', ['q' => 'quinn']))
            ->assertSee('Quinn Qualified')->assertDontSee('Cara Customer');

        $this->get(route('admin.contacts.index', ['has_email' => '0']))
            ->assertSee('Alice New')->assertDontSee('Cara Customer');

        // No results for filters
        $this->get(route('admin.contacts.index', ['q' => 'nobody-at-all']))
            ->assertSee('No contacts match these filters');

        // Active segment is marked
        $html = $this->get(route('admin.contacts.index', ['lead_stage' => 'customer']))->getContent();
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*Customers/s', $html);
    }

    public function test_page_scope_and_page_filter(): void
    {
        $this->contact(['customer_name' => 'Page One Person']);
        $this->contact(['customer_name' => 'Insta Person'], $this->otherPage);

        // All pages: both, plus the page filter works
        $this->get(route('admin.contacts.index'))->assertSee('Page One Person')->assertSee('Insta Person');
        $this->get(route('admin.contacts.index', ['page_id' => $this->otherPage->id]))
            ->assertSee('Insta Person')->assertDontSee('Page One Person');

        // Page switcher selects one page: the other page's contacts are hidden even with page_id
        app(CurrentPage::class)->set($this->page->id);
        $this->withSession([CurrentPage::SESSION_KEY => $this->page->id])
            ->get(route('admin.contacts.index', ['page_id' => $this->otherPage->id]))
            ->assertSee('Page One Person')->assertDontSee('Insta Person');
    }

    public function test_board_view_groups_contacts_by_stage(): void
    {
        $this->contact(['customer_name' => 'Board Newbie', 'lead_stage' => null]);
        $this->contact(['customer_name' => 'Board Buyer', 'lead_stage' => LeadStage::Customer]);

        $response = $this->get(route('admin.contacts.index', ['view' => 'board']))->assertOk();
        $board = $response->viewData('board');

        $this->assertCount(count(LeadStage::cases()), $board);
        $byStage = collect($board)->keyBy('stage');
        $this->assertSame(1, $byStage['new']['count']);
        $this->assertSame('Board Newbie', $byStage['new']['cards'][0]['name']);
        $this->assertSame('Board Buyer', $byStage['customer']['cards'][0]['name']);
        $response->assertSee('Drag cards between columns', false);
    }

    public function test_drawer_json_payload(): void
    {
        $c = $this->contact([
            'customer_name' => 'Drawer Dan', 'email' => 'dan@example.com', 'tags' => ['vip'], 'notes' => 'Likes blue',
            'meta' => ['captured' => [['type' => 'email', 'value' => 'dan@example.com', 'message_id' => 1, 'at' => now()->subDay()->toIso8601String()]]],
        ]);
        Message::factory()->count(7)->create(['conversation_id' => $c->id]);
        Message::factory()->fromBot()->create(['conversation_id' => $c->id, 'body' => 'Latest bot reply']);

        $json = $this->getJson(route('admin.contacts.show', $c))->assertOk()->json('data');

        $this->assertSame('Drawer Dan', $json['name']);
        $this->assertSame(['vip'], $json['tags']);
        $this->assertSame('Likes blue', $json['notes']);
        $this->assertSame(8, $json['stats']['messages']);
        $this->assertSame(1, $json['stats']['outgoing']);
        $this->assertCount(5, $json['messages']);
        $this->assertSame('Latest bot reply', end($json['messages'])['body']);
        $this->assertTrue(end($json['messages'])['mine']);
        $this->assertSame('dan@example.com', $json['captured'][0]['value']);
        $this->assertSame(route('admin.conversations.show', $c), $json['urls']['live_chat']);

        // Browser visit -> list with the drawer open
        $this->get(route('admin.contacts.show', $c))->assertRedirect(route('admin.contacts.index', ['contact' => $c->id]));
        $this->get(route('admin.contacts.index', ['contact' => $c->id]))->assertOk()->assertSee('Drawer Dan');

        $this->getJson(route('admin.contacts.show', 999999))->assertNotFound();
    }

    public function test_update_validates_and_saves(): void
    {
        $c = $this->contact(['customer_name' => 'Old Name']);

        $this->patchJson(route('admin.contacts.update', $c), ['email' => 'not-an-email', 'phone' => 'abc'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'phone']);

        $this->patchJson(route('admin.contacts.update', $c), ['lead_stage' => 'bogus'])->assertStatus(422)->assertJsonValidationErrors('lead_stage');

        $this->patchJson(route('admin.contacts.update', $c), [
            'customer_name' => 'New Name', 'email' => 'NEW@Example.com', 'phone' => '+92 300 123-4567',
            'lead_stage' => 'qualified', 'tags' => ['VIP ', 'vip', 'Wholesale'], 'notes' => 'Called back',
        ])->assertOk()->assertJsonPath('data.name', 'New Name')->assertJsonPath('message', 'Contact saved.');

        $c->refresh();
        $this->assertSame('new@example.com', $c->email);
        $this->assertSame('+923001234567', $c->phone);
        $this->assertSame(LeadStage::Qualified, $c->lead_stage);
        $this->assertSame(['vip', 'wholesale'], $c->tags);

        // Stage-only change (board drag) + clearing tags
        $this->patchJson(route('admin.contacts.update', $c), ['lead_stage' => 'customer'])->assertOk()->assertJsonPath('message', 'Moved to Customer.');
        $this->patchJson(route('admin.contacts.update', $c), ['tags' => null])->assertOk();
        $this->assertNull($c->refresh()->tags);
        $this->assertSame('New Name', $c->customer_name);
    }

    public function test_bulk_tag_untag_and_stage(): void
    {
        $a = $this->contact(['tags' => ['old']]);
        $b = $this->contact();
        $other = $this->contact([], $this->otherPage);

        $this->postJson(route('admin.contacts.bulk'), ['action' => 'add_tag', 'ids' => [$a->id, $b->id], 'tag' => ' VIP '])
            ->assertOk()->assertJsonPath('affected', 2);
        $this->assertTrue($a->refresh()->hasTag('vip'));
        $this->assertSame(['old', 'vip'], $a->tags);

        $this->postJson(route('admin.contacts.bulk'), ['action' => 'remove_tag', 'ids' => [$a->id, $b->id], 'tag' => 'old'])
            ->assertOk()->assertJsonPath('affected', 1);
        $this->assertSame(['vip'], $a->refresh()->tags);

        $this->postJson(route('admin.contacts.bulk'), ['action' => 'set_stage', 'ids' => [$a->id, $b->id], 'lead_stage' => 'lost'])
            ->assertOk()->assertJsonPath('message', 'Moved 2 contacts to Lost.');
        $this->assertSame(LeadStage::Lost, $b->refresh()->lead_stage);

        // "All matching" with filters
        $this->postJson(route('admin.contacts.bulk'), ['action' => 'add_tag', 'all' => 1, 'filters' => ['page_id' => $this->otherPage->id], 'tag' => 'ig'])
            ->assertOk()->assertJsonPath('affected', 1);
        $this->assertTrue($other->refresh()->hasTag('ig'));
        $this->assertFalse($a->refresh()->hasTag('ig'));

        // Validation
        $this->postJson(route('admin.contacts.bulk'), ['action' => 'add_tag', 'ids' => [$a->id]])->assertStatus(422)->assertJsonValidationErrors('tag');
        $this->postJson(route('admin.contacts.bulk'), ['action' => 'explode', 'ids' => [$a->id]])->assertStatus(422)->assertJsonValidationErrors('action');
        $this->postJson(route('admin.contacts.bulk'), ['action' => 'set_stage', 'ids' => []])->assertStatus(422);
    }

    public function test_bulk_respects_page_scope(): void
    {
        $mine = $this->contact();
        $theirs = $this->contact([], $this->otherPage);

        $this->withSession([CurrentPage::SESSION_KEY => $this->page->id])
            ->postJson(route('admin.contacts.bulk'), ['action' => 'add_tag', 'ids' => [$mine->id, $theirs->id], 'tag' => 'scoped'])
            ->assertOk()->assertJsonPath('affected', 1);

        $this->assertTrue($mine->refresh()->hasTag('scoped'));
        $this->assertFalse($theirs->refresh()->hasTag('scoped'));
    }

    public function test_bulk_delete_requires_confirmation_and_removes_messages(): void
    {
        $a = $this->contact();
        $b = $this->contact();
        $keep = $this->contact();
        Message::factory()->count(3)->create(['conversation_id' => $a->id]);
        Message::factory()->count(2)->create(['conversation_id' => $b->id]);
        Message::factory()->create(['conversation_id' => $keep->id]);

        $this->postJson(route('admin.contacts.bulk'), ['action' => 'delete', 'ids' => [$a->id, $b->id]])
            ->assertStatus(422)->assertJsonValidationErrors('confirm');

        $this->postJson(route('admin.contacts.bulk'), ['action' => 'delete', 'ids' => [$a->id, $b->id], 'confirm' => 'DELETE'])
            ->assertOk()->assertJsonPath('affected', 2);

        $this->assertDatabaseMissing('conversations', ['id' => $a->id]);
        $this->assertDatabaseMissing('conversations', ['id' => $b->id]);
        $this->assertSame(0, Message::whereIn('conversation_id', [$a->id, $b->id])->count());
        $this->assertSame(1, Message::where('conversation_id', $keep->id)->count());
    }

    public function test_export_csv_respects_filters_and_selection(): void
    {
        $this->contact(['customer_name' => 'Export Emma', 'email' => 'emma@example.com', 'lead_stage' => LeadStage::Customer]);
        $this->contact(['customer_name' => 'Export Nora', 'lead_stage' => LeadStage::New]);
        $ig = $this->contact(['customer_name' => 'Export Ivy'], $this->otherPage);

        $response = $this->get(route('admin.contacts.export', ['lead_stage' => 'customer']));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=contacts-', $response->headers->get('Content-Disposition'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Name,Username,Platform,Page,Email,Phone,Stage,Tags', $csv);
        $this->assertStringContainsString('Export Emma', $csv);
        $this->assertStringNotContainsString('Export Nora', $csv);

        $selected = $this->post(route('admin.contacts.export'), ['ids' => [$ig->id]]);
        $selected->assertOk();
        $csv = $selected->streamedContent();
        $this->assertStringContainsString('Export Ivy', $csv);
        $this->assertStringNotContainsString('Export Emma', $csv);
        $this->assertStringContainsString('contacts-selected-', $selected->headers->get('Content-Disposition'));

        // Page scope wins over the selection
        $scoped = $this->withSession([CurrentPage::SESSION_KEY => $this->page->id])->post(route('admin.contacts.export'), ['ids' => [$ig->id]]);
        $this->assertStringNotContainsString('Export Ivy', $scoped->streamedContent());
    }

    public function test_delete_contact_requires_typed_confirmation_and_removes_everything(): void
    {
        $c = $this->contact(['customer_name' => 'Gone Soon']);
        Message::factory()->count(4)->create(['conversation_id' => $c->id]);

        $this->deleteJson(route('admin.contacts.destroy', $c), ['confirm' => 'delete'])
            ->assertStatus(422)->assertJsonValidationErrors('confirm');
        $this->assertDatabaseHas('conversations', ['id' => $c->id]);

        $this->deleteJson(route('admin.contacts.destroy', $c), ['confirm' => 'DELETE'])
            ->assertOk()
            ->assertJsonPath('deleted.messages', 4)
            ->assertJsonPath('message', 'Gone Soon and 4 messages deleted.');

        $this->assertDatabaseMissing('conversations', ['id' => $c->id]);
        $this->assertSame(0, Message::where('conversation_id', $c->id)->count());
    }

    public function test_refresh_profile_queues_the_fetch(): void
    {
        Queue::fake();
        $c = $this->contact();

        $this->postJson(route('admin.contacts.refresh', $c))->assertOk()->assertJsonStructure(['message']);

        Queue::assertPushed(FetchContactProfile::class);
    }

    public function test_csrf_is_required_for_mutations(): void
    {
        $c = $this->contact();
        $html = $this->get(route('admin.contacts.index'))->getContent();

        $this->assertStringContainsString('name="csrf-token"', $html);
        $this->assertStringContainsString('name="_token"', $html); // export-selected form
    }
}
