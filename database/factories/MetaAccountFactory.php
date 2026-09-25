<?php

namespace Database\Factories;

use App\Enums\Platform;
use App\Models\MetaAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MetaAccount> */
class MetaAccountFactory extends Factory
{
    protected $model = MetaAccount::class;

    public function definition(): array
    {
        return [
            'platform' => Platform::Facebook,
            'auth_type' => MetaAccount::AUTH_FACEBOOK_LOGIN,
            'page_id' => (string) fake()->unique()->numerify('1############'),
            'instagram_account_id' => null,
            'page_name' => fake()->company(),
            'access_token' => 'EAAtest'.fake()->sha256(),
            'active' => true,
        ];
    }

    public function instagram(): static
    {
        return $this->state(fn () => [
            'platform' => Platform::Instagram,
            'instagram_account_id' => (string) fake()->unique()->numerify('178414##########'),
        ]);
    }
}
