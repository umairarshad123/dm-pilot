<?php

namespace Tests\Feature\Contacts;

use App\Jobs\FetchContactProfile;
use App\Models\Conversation;
use App\Models\MetaAccount;
use App\Observers\ConversationObserver;
use App\Services\Contacts\ContactProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FetchContactProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_messenger_profile_is_fetched_with_the_page_token(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response([
            'first_name' => 'Jane', 'last_name' => 'Doe', 'profile_pic' => 'https://scontent.xx.fbcdn.net/p.jpg', 'id' => '2001',
        ])]);
        $account = MetaAccount::factory()->create();
        $conversation = Conversation::factory()->for($account)->create(['external_user_id' => '2001']);

        FetchContactProfile::dispatchSync($conversation->id);

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://graph.facebook.com/v26.0/2001?')
            && $r['fields'] === 'first_name,last_name,profile_pic'
            && $r->hasHeader('Authorization', 'Bearer '.$account->access_token)
            && ! str_contains($r->url(), 'access_token'));

        $conversation->refresh();
        $this->assertSame('Jane', $conversation->first_name);
        $this->assertSame('Doe', $conversation->last_name);
        $this->assertSame('Jane Doe', $conversation->customer_name);
        $this->assertSame('https://scontent.xx.fbcdn.net/p.jpg', $conversation->profile_pic_url);
        $this->assertNotNull($conversation->profile_fetched_at);
    }

    public function test_instagram_profile_uses_instagram_fields(): void
    {
        Http::fake(['graph.instagram.com/*' => Http::response(['name' => 'Ali Raza Khan', 'username' => 'ali.k', 'profile_pic' => 'https://cdn.ig/p.jpg'])]);
        $account = MetaAccount::factory()->instagram()->create(['auth_type' => MetaAccount::AUTH_INSTAGRAM_LOGIN]);
        $conversation = Conversation::factory()->for($account)->create(['external_user_id' => '3001']);

        FetchContactProfile::dispatchSync($conversation->id);

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://graph.instagram.com/v26.0/3001?')
            && $r['fields'] === 'name,username,profile_pic');

        $conversation->refresh();
        $this->assertSame('Ali', $conversation->first_name);
        $this->assertSame('Raza Khan', $conversation->last_name);
        $this->assertSame('ali.k', $conversation->username);
        $this->assertSame('Ali Raza Khan', $conversation->customer_name);
    }

    public function test_manual_name_is_kept_but_auto_name_is_refreshed(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::sequence()
            ->push(['first_name' => 'Jane', 'last_name' => 'Doe'])
            ->push(['first_name' => 'Janet', 'last_name' => 'Doe'])
            ->push(['first_name' => 'Jo', 'last_name' => 'Doe'])]);
        $conversation = Conversation::factory()->create();

        FetchContactProfile::dispatchSync($conversation->id);
        FetchContactProfile::dispatchSync($conversation->id); // within TTL, not forced: skipped
        $this->assertSame('Jane Doe', $conversation->fresh()->customer_name);

        FetchContactProfile::dispatchSync($conversation->id, true);
        $this->assertSame('Janet Doe', $conversation->fresh()->customer_name);

        $conversation->fresh()->forceFill(['customer_name' => 'VIP Janet'])->save();
        FetchContactProfile::dispatchSync($conversation->id, true);
        $this->assertSame('VIP Janet', $conversation->fresh()->customer_name);
        $this->assertSame('Jo', $conversation->fresh()->first_name);
    }

    public function test_permission_errors_are_logged_at_info_and_ignored(): void
    {
        $logs = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logs) {
            $logs[] = $e;
        });
        Http::fake(['*' => Http::response(['error' => ['code' => 230, 'message' => 'Requires pages_messaging permission EAAsecrettoken1234567890']], 400)]);
        $conversation = Conversation::factory()->create(['customer_name' => 'Kept']);

        FetchContactProfile::dispatchSync($conversation->id);

        $conversation->refresh();
        $this->assertSame('Kept', $conversation->customer_name);
        $this->assertNotNull($conversation->profile_fetched_at);
        $this->assertSame(['info'], array_values(array_unique(array_map(fn ($l) => $l->level, $logs))));
        $this->assertStringNotContainsString('EAAsecret', json_encode(array_map(fn ($l) => [$l->message, $l->context], $logs)));
        $this->assertStringNotContainsString('EAAsecret', json_encode($conversation->meta));
    }

    public function test_retryable_errors_release_the_job(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 613, 'message' => 'rate limit']], 400)]);
        $conversation = Conversation::factory()->create();

        $job = (new FetchContactProfile($conversation->id))->withFakeQueueInteractions();
        $job->handle(app(ContactProfileService::class));

        $job->assertReleased();
        $this->assertNull($conversation->fresh()->profile_fetched_at);
    }

    public function test_unexpected_response_shapes_are_tolerated(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['recipient_id' => '1', 'message_id' => 'm_1'])
            ->push(['first_name' => ['nested'], 'profile_pic' => 'not-a-url', 'last_name' => 42])
            ->push('plain text body')]);
        $conversation = Conversation::factory()->create();

        FetchContactProfile::dispatchSync($conversation->id, true);
        FetchContactProfile::dispatchSync($conversation->id, true);
        FetchContactProfile::dispatchSync($conversation->id, true);

        $conversation->refresh();
        $this->assertNull($conversation->first_name);
        $this->assertSame('42', $conversation->last_name);
        $this->assertNull($conversation->profile_pic_url);
    }

    public function test_observer_queues_fetch_on_async_queue_only(): void
    {
        Queue::fake();

        config(['queue.default' => 'sync']);
        Conversation::factory()->create();
        Queue::assertNotPushed(FetchContactProfile::class);

        config(['queue.default' => 'database']);
        $conversation = Conversation::factory()->create();
        Queue::assertPushed(FetchContactProfile::class, fn ($job) => $job->conversationId === $conversation->id && ! $job->force);

        config(['contacts.fetch_profiles' => false]);
        $this->assertFalse(ConversationObserver::shouldFetchProfiles());

        config(['contacts.fetch_profiles' => true, 'queue.default' => 'sync', 'contacts.fetch_profiles_on_sync_queue' => true]);
        $this->assertTrue(ConversationObserver::shouldFetchProfiles());
    }
}
