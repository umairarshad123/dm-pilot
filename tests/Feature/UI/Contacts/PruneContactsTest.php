<?php

namespace Tests\Feature\UI\Contacts;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\WebhookEvent;
use App\Services\Contacts\ContactDeletionService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PruneContactsTest extends TestCase
{
    use RefreshDatabase;

    private function contactActive(string $when, int $messages = 2): Conversation
    {
        $c = Conversation::factory()->create(['last_message_at' => now()->modify($when)]);
        Message::factory()->count($messages)->create(['conversation_id' => $c->id]);

        return $c;
    }

    public function test_dry_run_only_counts(): void
    {
        $this->contactActive('-13 months', 3);
        $this->contactActive('-1 month');

        $this->artisan('contacts:prune', ['--dry-run' => true])
            ->expectsOutputToContain('[dry run] Would delete 1 contact(s) and 3 message(s)')
            ->assertSuccessful();

        $this->assertSame(2, Conversation::count());
        $this->assertSame(5, Message::count());
    }

    public function test_prunes_contacts_older_than_the_configured_retention(): void
    {
        config(['legal.retention.conversations_months' => 12]);
        $old = $this->contactActive('-13 months', 3);
        $older = $this->contactActive('-3 years', 1);
        $recent = $this->contactActive('-11 months', 2);
        $neverActive = Conversation::factory()->create(['last_message_at' => null]);
        $neverActive->forceFill(['created_at' => now()->subMonths(20)])->save();

        $this->artisan('contacts:prune')
            ->expectsOutputToContain('Deleted 3 contact(s) and 4 message(s)')
            ->assertSuccessful();

        $this->assertDatabaseMissing('conversations', ['id' => $old->id]);
        $this->assertDatabaseMissing('conversations', ['id' => $older->id]);
        $this->assertDatabaseMissing('conversations', ['id' => $neverActive->id]);
        $this->assertDatabaseHas('conversations', ['id' => $recent->id]);
        $this->assertSame(2, Message::count());
    }

    public function test_months_option_overrides_the_cutoff_and_chunks(): void
    {
        $this->contactActive('-4 months');
        $this->contactActive('-5 months');
        $keep = $this->contactActive('-2 months');

        $this->artisan('contacts:prune', ['--months' => 3, '--chunk' => 1])
            ->expectsOutputToContain('Deleted 2 contact(s)')
            ->assertSuccessful();

        $this->assertSame([$keep->id], Conversation::pluck('id')->all());
    }

    public function test_zero_months_disables_pruning_and_bad_values_fail(): void
    {
        $this->contactActive('-5 years');
        config(['legal.retention.conversations_months' => 0]);

        $this->artisan('contacts:prune')->expectsOutputToContain('disabled')->assertSuccessful();
        $this->assertSame(1, Conversation::count());

        $this->artisan('contacts:prune', ['--months' => 'abc'])->assertFailed();
    }

    public function test_prune_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command);

        $this->assertTrue($events->contains(fn ($cmd) => str_contains((string) $cmd, 'contacts:prune')));
        $this->assertTrue($events->contains(fn ($cmd) => str_contains((string) $cmd, 'meta:prune-webhook-events')));
    }

    public function test_manual_delete_purges_webhook_events_and_logs_without_content(): void
    {
        $c = Conversation::factory()->create(['external_user_id' => '24681357900001', 'customer_name' => 'Secret Sam']);
        Message::factory()->create(['conversation_id' => $c->id, 'body' => 'my private message']);
        WebhookEvent::query()->forceCreate([
            'object' => 'page', 'status' => 'processed', 'payload' => ['entry' => [['messaging' => [['sender' => ['id' => '24681357900001']]]]]],
        ] + $this->webhookDefaults());
        WebhookEvent::query()->forceCreate([
            'object' => 'page', 'status' => 'processed', 'payload' => ['entry' => [['messaging' => [['sender' => ['id' => '99999999999999']]]]]],
        ] + $this->webhookDefaults());

        $logged = [];
        Log::shouldReceive('channel')->with('meta')->andReturnSelf();
        Log::shouldReceive('info')->andReturnUsing(function ($msg, $ctx) use (&$logged) {
            $logged[] = [$msg, $ctx];
        });

        $counts = app(ContactDeletionService::class)->delete($c, 'manual', 1);

        $this->assertSame(['conversations' => 1, 'messages' => 1, 'webhook_events' => 1], $counts);
        $this->assertSame(1, WebhookEvent::count());
        $this->assertSame('Contact deleted.', $logged[0][0]);
        $encoded = json_encode($logged);
        $this->assertStringNotContainsString('my private message', $encoded);
        $this->assertStringNotContainsString('Secret Sam', $encoded);
        $this->assertStringNotContainsString('24681357900001', $encoded);
    }

    /** Columns the webhook_events table requires besides object/status/payload. */
    private function webhookDefaults(): array
    {
        $columns = Schema::getColumnListing('webhook_events');
        $defaults = [];

        if (in_array('payload_hash', $columns, true)) {
            $defaults['payload_hash'] = bin2hex(random_bytes(16));
        }
        if (in_array('signature_valid', $columns, true)) {
            $defaults['signature_valid'] = true;
        }

        return $defaults;
    }
}
