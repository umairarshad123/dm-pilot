<?php

namespace Database\Factories;

use App\Models\AutomationRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AutomationRule> */
class AutomationRuleFactory extends Factory
{
    protected $model = AutomationRule::class;

    public function definition(): array
    {
        return [
            'meta_account_id' => null,
            'name' => fake()->words(3, true),
            'trigger' => AutomationRule::TRIGGER_KEYWORD,
            'match_type' => AutomationRule::MATCH_CONTAINS,
            'keywords' => ['price'],
            'reply_text' => 'Our prices start at $20.',
            'priority' => 0,
            'active' => true,
        ];
    }

    public function welcome(string $reply = 'Welcome! How can we help?'): static
    {
        return $this->state(fn () => [
            'trigger' => AutomationRule::TRIGGER_WELCOME,
            'keywords' => null,
            'reply_text' => $reply,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
