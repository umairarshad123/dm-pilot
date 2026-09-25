<?php

namespace Tests\Feature\Public;

use App\Models\Conversation;
use App\Models\DataDeletionRequest;
use App\Models\Message;
use App\Models\MetaAccount;
use App\Models\WebhookEvent;
use App\Services\Meta\SignedRequest;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataDeletionCallbackTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'callback-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['meta.app_secret' => self::SECRET]);
    }

    private function signed(array $payload, string $secret = self::SECRET): string
    {
        return (new SignedRequest($secret))->make($payload);
    }

    private function conversationFor(string $externalId): Conversation
    {
        $conversation = Conversation::factory()->create(['external_user_id' => $externalId]);
        Message::factory()->count(2)->create(['conversation_id' => $conversation->id]);

        return $conversation;
    }

    public function test_valid_request_deletes_matching_data_and_returns_url_and_code(): void
    {
        $target = $this->conversationFor('1234567890123');
        $other = $this->conversationFor('9999999999999');
        WebhookEvent::create(['object' => 'page', 'payload_hash' => hash('sha256', 'a'), 'payload' => ['sender' => ['id' => '1234567890123']]]);
        WebhookEvent::create(['object' => 'page', 'payload_hash' => hash('sha256', 'b'), 'payload' => ['sender' => ['id' => '9999999999999']]]);

        $response = $this->post('/meta/data-deletion', ['signed_request' => $this->signed(['user_id' => '1234567890123'])]);

        $response->assertOk()->assertJsonStructure(['url', 'confirmation_code']);

        $code = $response->json('confirmation_code');
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{20}$/', $code);
        $this->assertSame(route('meta.data-deletion.status', $code), $response->json('url'));

        $request = DataDeletionRequest::firstWhere('confirmation_code', $code);
        $this->assertSame('1234567890123', $request->meta_user_id);
        $this->assertSame(DataDeletionRequest::STATUS_COMPLETED, $request->status);
        $this->assertSame(1, $request->conversations_deleted);
        $this->assertSame(2, $request->messages_deleted);
        $this->assertSame(1, $request->webhook_events_deleted);
        $this->assertNotNull($request->completed_at);

        $this->assertModelMissing($target);
        $this->assertSame(0, Message::where('conversation_id', $target->id)->count());
        $this->assertModelExists($other);
        $this->assertSame(2, Message::where('conversation_id', $other->id)->count());
        $this->assertSame(1, WebhookEvent::count());
    }

    public function test_request_with_no_matching_data_is_recorded_as_no_data(): void
    {
        $this->post('/meta/data-deletion', ['signed_request' => $this->signed(['user_id' => '555'])])->assertOk();

        $this->assertSame(DataDeletionRequest::STATUS_NO_DATA, DataDeletionRequest::sole()->status);
    }

    public function test_callback_is_csrf_exempt_and_json_only(): void
    {
        // The test kernel skips CSRF anyway; assert the route explicitly drops the middleware.
        $route = app('router')->getRoutes()->getByName('meta.data-deletion');
        $this->assertContains(ValidateCsrfToken::class, $route->excludedMiddleware());
        $this->assertContains('web', $route->middleware());
    }

    public function test_invalid_signature_is_rejected_and_nothing_deleted(): void
    {
        $conversation = $this->conversationFor('123');

        $this->post('/meta/data-deletion', ['signed_request' => $this->signed(['user_id' => '123'], 'wrong-secret')])
            ->assertStatus(400);

        $this->post('/meta/data-deletion', [])->assertStatus(400);

        $this->assertModelExists($conversation);
        $this->assertSame(0, DataDeletionRequest::count());
    }

    public function test_missing_user_id_is_rejected(): void
    {
        $this->post('/meta/data-deletion', ['signed_request' => $this->signed(['foo' => 'bar'])])->assertStatus(400);
        $this->assertSame(0, DataDeletionRequest::count());
    }

    public function test_expired_signed_request_is_rejected(): void
    {
        config(['legal.signed_request_max_age' => 3600]);

        $this->post('/meta/data-deletion', ['signed_request' => $this->signed(['user_id' => '1', 'issued_at' => time() - 7200])])
            ->assertStatus(400);
    }

    public function test_status_page_shows_request(): void
    {
        $request = DataDeletionRequest::factory()->create(['confirmation_code' => 'ABCDEFGH12345678WXYZ']);

        $this->get('/meta/data-deletion/ABCDEFGH12345678WXYZ')
            ->assertOk()
            ->assertSee('ABCDEFGH12345678WXYZ')
            ->assertSee('Completed');

        $this->get(route('meta.data-deletion.status', strtolower($request->confirmation_code)))->assertOk();
    }

    public function test_status_page_pending_and_unknown_code(): void
    {
        DataDeletionRequest::factory()->pending()->create(['confirmation_code' => 'PENDING1234567890ABC']);

        $this->get('/meta/data-deletion/PENDING1234567890ABC')->assertOk()->assertSee('in progress');
        $this->get('/meta/data-deletion/DOESNOTEXIST12345678')->assertNotFound();
    }

    public function test_status_page_does_not_expose_deauthorize_records(): void
    {
        DataDeletionRequest::factory()->create(['confirmation_code' => 'DEAUTH12345678901234', 'type' => DataDeletionRequest::TYPE_DEAUTHORIZE]);

        $this->get('/meta/data-deletion/DEAUTH12345678901234')->assertNotFound();
    }

    public function test_deauthorize_records_and_deactivates_linked_accounts(): void
    {
        $linked = MetaAccount::factory()->create(['settings' => ['connected_by_user_id' => '777000111']]);
        $unrelated = MetaAccount::factory()->create(['settings' => ['connected_by_user_id' => '888']]);

        $this->post('/meta/deauthorize', ['signed_request' => $this->signed(['user_id' => '777000111'])])->assertOk();

        $record = DataDeletionRequest::sole();
        $this->assertSame(DataDeletionRequest::TYPE_DEAUTHORIZE, $record->type);
        $this->assertSame('777000111', $record->meta_user_id);
        $this->assertSame(1, $record->accounts_affected);

        $linked->refresh();
        $this->assertFalse($linked->active);
        $this->assertSame('777000111', $linked->settings['deauthorized_by']);
        $this->assertTrue($unrelated->refresh()->active);
    }

    public function test_deauthorize_rejects_invalid_signature(): void
    {
        $this->post('/meta/deauthorize', ['signed_request' => 'garbage.payload'])->assertStatus(400);
        $this->assertSame(0, DataDeletionRequest::count());
    }
}
