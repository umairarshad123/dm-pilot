<?php

namespace Tests\Feature\UI\Automations;

use App\Models\MetaAccount;
use App\Services\Meta\MetaMessagingService;
use Mockery\MockInterface;
use Tests\Feature\Admin\AdminTestCase;

class MessengerProfileAdminTest extends AdminTestCase
{
    public function test_load_current_profile_from_meta(): void
    {
        $page = MetaAccount::factory()->create(['access_token' => 'EAAB-secret-token']);
        $this->mock(MetaMessagingService::class, fn (MockInterface $m) => $m->shouldReceive('getMessengerProfile')->once()
            ->andReturn(['ok' => true, 'profile' => ['greeting' => 'Hi!', 'get_started' => true, 'ice_breakers' => [['question' => 'Hours?', 'payload' => 'HOURS']]]]));

        $this->getJson(route('admin.automations.profile.show', $page))
            ->assertOk()
            ->assertJsonPath('profile.greeting', 'Hi!')
            ->assertJsonPath('profile.ice_breakers.0.payload', 'HOURS')
            ->assertDontSee('EAAB-secret-token');
    }

    public function test_load_failure_is_a_422_with_the_meta_error(): void
    {
        $page = MetaAccount::factory()->create();
        $this->mock(MetaMessagingService::class, fn (MockInterface $m) => $m->shouldReceive('getMessengerProfile')
            ->andReturn(['ok' => false, 'error' => 'Invalid OAuth access token.']));

        $this->getJson(route('admin.automations.profile.show', $page))
            ->assertUnprocessable()->assertJson(['message' => 'Meta: Invalid OAuth access token.']);
    }

    public function test_save_sends_normalised_config_and_returns_warnings(): void
    {
        $page = MetaAccount::factory()->create();
        $this->mock(MetaMessagingService::class, fn (MockInterface $m) => $m->shouldReceive('setMessengerProfile')->once()
            ->withArgs(fn ($account, array $config) => $account->is($page) && $config === [
                'ice_breakers' => [['question' => 'Opening hours?', 'payload' => null], ['question' => 'Delivery?', 'payload' => 'DELIVERY']],
                'greeting' => 'Welcome!',
                'get_started' => true,
            ])
            ->andReturn(['ok' => true, 'profile' => ['greeting' => 'Welcome!', 'updated_at' => now()->toIso8601String()], 'warnings' => ['Meta shows ice breakers instead of the Get Started button when both are set.']]));

        $this->putJson(route('admin.automations.profile.update', $page), [
            'greeting' => ' Welcome! ',
            'get_started' => true,
            'ice_breakers' => [
                ['question' => 'Opening hours?', 'payload' => ''],
                ['question' => '  ', 'payload' => ''],
                ['question' => 'Delivery?', 'payload' => 'DELIVERY'],
            ],
        ])->assertOk()->assertJsonPath('warnings.0', 'Meta shows ice breakers instead of the Get Started button when both are set.');
    }

    public function test_instagram_only_sends_ice_breakers(): void
    {
        $page = MetaAccount::factory()->instagram()->create();
        $this->mock(MetaMessagingService::class, fn (MockInterface $m) => $m->shouldReceive('setMessengerProfile')->once()
            ->withArgs(fn ($account, array $config) => array_keys($config) === ['ice_breakers'])
            ->andReturn(['ok' => true, 'profile' => []]));

        $this->putJson(route('admin.automations.profile.update', $page), ['greeting' => 'ignored', 'get_started' => true, 'ice_breakers' => [['question' => 'Hi?']]])
            ->assertOk();
    }

    public function test_save_validates_limits_before_calling_meta(): void
    {
        $page = MetaAccount::factory()->create();
        $this->mock(MetaMessagingService::class, fn (MockInterface $m) => $m->shouldNotReceive('setMessengerProfile'));

        $this->putJson(route('admin.automations.profile.update', $page), [
            'greeting' => str_repeat('a', MetaMessagingService::GREETING_MAX_CHARS + 1),
            'ice_breakers' => array_fill(0, MetaMessagingService::ICE_BREAKERS_MAX + 1, ['question' => 'Q?']),
        ])->assertUnprocessable()->assertJsonValidationErrors(['greeting', 'ice_breakers']);

        $this->putJson(route('admin.automations.profile.update', $page), [
            'ice_breakers' => [['question' => str_repeat('q', MetaMessagingService::ICE_BREAKER_QUESTION_MAX_CHARS + 1), 'payload' => 'bad payload!']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['ice_breakers.0.question', 'ice_breakers.0.payload']);
    }

    public function test_meta_error_on_save_is_reported(): void
    {
        $page = MetaAccount::factory()->create();
        $this->mock(MetaMessagingService::class, fn (MockInterface $m) => $m->shouldReceive('setMessengerProfile')
            ->andReturn(['ok' => false, 'error' => '(#100) Param greeting is invalid']));

        $this->putJson(route('admin.automations.profile.update', $page), ['greeting' => 'Hi', 'get_started' => false, 'ice_breakers' => []])
            ->assertUnprocessable()->assertJson(['message' => 'Meta: (#100) Param greeting is invalid']);
    }
}
