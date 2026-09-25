<?php

namespace Database\Factories;

use App\Models\DataDeletionRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<DataDeletionRequest> */
class DataDeletionRequestFactory extends Factory
{
    protected $model = DataDeletionRequest::class;

    public function definition(): array
    {
        return [
            'confirmation_code' => Str::upper(Str::random(20)),
            'type' => DataDeletionRequest::TYPE_DELETION,
            'meta_user_id' => (string) fake()->numerify('1###############'),
            'status' => DataDeletionRequest::STATUS_COMPLETED,
            'issued_at' => now(),
            'completed_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => DataDeletionRequest::STATUS_PENDING, 'completed_at' => null]);
    }
}
