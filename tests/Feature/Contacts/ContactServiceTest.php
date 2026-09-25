<?php

namespace Tests\Feature\Contacts;

use App\Enums\LeadStage;
use App\Enums\MessageStatus;
use App\Jobs\FetchContactProfile;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetaAccount;
use App\Services\Contacts\ContactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContactServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContactService $service;

    private MetaAccount $fb;

    private MetaAccount $ig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ContactService::class);
        $this->fb = MetaAccount::factory()->create(['page_name' => 'Shop FB']);
        $this->ig = MetaAccount::factory()->instagram()->create(['page_name' => 'Shop IG']);
    }

    /** @return list<int> */
    private function ids(array $filters): array
    {
        return collect($this->service->paginate($filters)->items())->pluck('id')->all();
    }

    public function test_filters_and_sorting(): void
    {
        $jane = Conversation::factory()->for($this->fb)->create([
            'customer_name' => 'Jane Doe', 'email' => 'jane@x.com', 'phone' => '+923001234567',
            'tags' => ['vip'], 'lead_stage' => LeadStage::Qualified, 'last_message_at' => now()->subHour(),
        ]);
        $ali = Conversation::factory()->for($this->ig)->create([
            'username' => 'ali_k', 'tags' => ['vip', 'wholesale'], 'last_message_at' => now(), 'unread_count' => 3,
        ]);
        $legacy = Conversation::factory()->for($this->fb)->create(['last_message_at' => now()->subDay()]);
        Conversation::query()->whereKey($legacy->id)->update(['lead_stage' => null]);

        $this->assertSame([$ali->id, $jane->id, $legacy->id], $this->ids([]));
        $this->assertSame([$legacy->id, $jane->id, $ali->id], $this->ids(['sort' => 'oldest']));
        $this->assertSame([$jane->id, $legacy->id], $this->ids(['page_id' => $this->fb->id]));
        $this->assertSame([$ali->id], $this->ids(['platform' => 'instagram']));
        $this->assertSame([$ali->id], $this->ids(['search' => '@ali']));
        $this->assertSame([$jane->id], $this->ids(['search' => 'jane doe']));
        $this->assertSame([$jane->id], $this->ids(['search' => '0300 1234567'])); // digits-only phone match
        $this->assertSame([$ali->id, $jane->id], $this->ids(['tag' => ' VIP ']));
        $this->assertSame([$ali->id], $this->ids(['tag' => 'wholesale']));
        $this->assertSame([$jane->id], $this->ids(['lead_stage' => 'qualified']));
        $this->assertSame([$ali->id, $legacy->id], $this->ids(['lead_stage' => 'new'])); // includes NULL
        $this->assertSame([$jane->id], $this->ids(['has_email' => '1']));
        $this->assertSame([$ali->id, $legacy->id], $this->ids(['has_phone' => 'false']));
        $this->assertSame([$ali->id], $this->ids(['unread' => true]));
        $this->assertSame([$ali->id, $jane->id, $legacy->id], $this->ids(['sort' => 'unread', 'platform' => 'bogus']));

        $page = $this->service->paginate([], 2);
        $this->assertSame(3, $page->total());
        $this->assertTrue($page->items()[0]->relationLoaded('metaAccount'));
        $this->assertSame(0, $page->items()[0]->messages_count);
    }

    public function test_tags_helpers_and_all_tags(): void
    {
        $a = Conversation::factory()->for($this->fb)->create();
        $b = Conversation::factory()->for($this->ig)->create();

        $a->addTag('VIP', ' Wholesale ', 'vip');
        $b->syncTags(['vip', 'returning']);
        $b->removeTag('RETURNING');

        $this->assertSame(['vip', 'wholesale'], $a->fresh()->tags);
        $this->assertTrue($a->fresh()->hasTag('Wholesale'));
        $this->assertSame(['vip'], $b->fresh()->tags);
        $this->assertSame(['vip', 'wholesale'], $this->service->allTags());
        $this->assertSame(['vip' => 2, 'wholesale' => 1], $this->service->tagCounts());
        $this->assertSame(['vip'], $this->service->allTags($this->ig->id));

        $b->removeTag('vip');
        $this->assertNull($b->fresh()->tags);
    }

    public function test_update_validates_and_normalizes(): void
    {
        $contact = Conversation::factory()->for($this->fb)->create(['notes' => 'keep']);

        $this->service->update($contact, [
            'customer_name' => '  Jane  ', 'email' => 'Jane@X.com', 'phone' => '+92 300 1234567',
            'lead_stage' => 'customer', 'tags' => 'VIP, wholesale, vip',
        ]);

        $contact->refresh();
        $this->assertSame('Jane', $contact->customer_name);
        $this->assertSame('jane@x.com', $contact->email);
        $this->assertSame('+923001234567', $contact->phone);
        $this->assertSame(LeadStage::Customer, $contact->lead_stage);
        $this->assertSame(['vip', 'wholesale'], $contact->tags);
        $this->assertSame('keep', $contact->notes); // untouched: not in the input

        $this->service->update($contact, ['email' => '', 'tags' => []]);
        $this->assertNull($contact->fresh()->email);
        $this->assertNull($contact->fresh()->tags);

        try {
            $this->service->update($contact, ['email' => 'nope', 'lead_stage' => 'boss', 'phone' => 'call me']);
            $this->fail('Expected validation error');
        } catch (ValidationException $e) {
            $this->assertEqualsCanonicalizing(['email', 'lead_stage', 'phone'], array_keys($e->errors()));
        }

        $this->assertArrayHasKey('notes', ContactService::rules());
    }

    public function test_refresh_profile_dispatches_forced_job(): void
    {
        Queue::fake();
        $contact = Conversation::factory()->for($this->fb)->create();

        $this->service->refreshProfile($contact);

        Queue::assertPushed(FetchContactProfile::class, fn ($job) => $job->conversationId === $contact->id && $job->force);
    }

    public function test_csv_export(): void
    {
        $contact = Conversation::factory()->for($this->fb)->create([
            'customer_name' => '=HYPERLINK("x")', 'email' => 'a@b.com', 'phone' => '+923001234567', 'tags' => ['vip', 'b2b'],
            'lead_stage' => LeadStage::Qualified,
        ]);
        Message::factory()->for($contact)->create(['created_at' => now()->subDays(2)]);
        Message::factory()->for($contact)->create(['created_at' => now()->subDay()]);
        Message::factory()->for($contact)->fromBot()->create(['created_at' => now()]);
        Conversation::factory()->for($this->ig)->create(['username' => 'other']);

        $response = $this->service->exportCsv(['page_id' => $this->fb->id], 'contacts.csv');

        $this->assertStringContainsString('attachment; filename=contacts.csv', $response->headers->get('Content-Disposition'));
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $lines = array_map('str_getcsv', explode("\n", trim(substr($csv, 3))));
        $this->assertCount(2, $lines);
        $this->assertSame(ContactService::EXPORT_COLUMNS, $lines[0]);
        $row = array_combine($lines[0], $lines[1]);
        $this->assertSame("'=HYPERLINK(\"x\")", $row['Name']);
        $this->assertSame('facebook', $row['Platform']);
        $this->assertSame('Shop FB', $row['Page']);
        $this->assertSame('+923001234567', $row['Phone']);
        $this->assertSame('Qualified', $row['Stage']);
        $this->assertSame('vip, b2b', $row['Tags']);
        $this->assertSame(now()->subDays(2)->format('Y-m-d'), substr($row['First message at'], 0, 10));
        $this->assertSame(['2', '1', '3'], [$row['Incoming messages'], $row['Outgoing messages'], $row['Total messages']]);
        $this->assertSame(MessageStatus::Sent, Message::latest('id')->first()->status);
    }
}
