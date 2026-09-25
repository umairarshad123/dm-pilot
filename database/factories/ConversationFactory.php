<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\MetaAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Conversation> */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'meta_account_id' => MetaAccount::factory(),
            'platform' => fn (array $attrs) => MetaAccount::find($attrs['meta_account_id'])->platform,
            'external_user_id' => (string) fake()->unique()->numerify('2###############'),
            'status' => 'open',
            'bot_enabled' => true,
            'human_takeover' => false,
            'last_message_at' => now(),
            'last_customer_message_at' => now(),
        ];
    }

    /** A contact with a filled profile. */
    public function withProfile(): static
    {
        return $this->state(fn () => [
            'customer_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+'.fake()->numerify('92##########'),
        ]);
    }
}
