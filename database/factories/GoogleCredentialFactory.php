<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GoogleCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleCredential>
 */
class GoogleCredentialFactory extends Factory
{
    protected $model = GoogleCredential::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->coach(),
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'token_expires_at' => now()->addHour(),
            'calendar_id' => fake()->uuid(),
            'connected_at' => now(),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
        ]);
    }
}
