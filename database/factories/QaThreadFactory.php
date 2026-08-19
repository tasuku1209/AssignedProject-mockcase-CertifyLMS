<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QaThread>
 */
class QaThreadFactory extends Factory
{
    protected $model = QaThread::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'certification_id' => Certification::factory()->published(),
            'title' => fake()->sentence(3),
            'body' => fake()->paragraph(),
            'status' => QaThreadStatus::Unresolved->value,
            'resolved_at' => null,
        ];
    }

    /**
     * 解決済みの質問。
     */
    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => QaThreadStatus::Resolved->value,
            'resolved_at' => now(),
        ]);
    }

    /**
     * 未解決の質問。
     */
    public function unresolved(): static
    {
        return $this->state(fn () => [
            'status' => QaThreadStatus::Unresolved->value,
            'resolved_at' => null,
        ]);
    }

    /**
     * 指定した資格に紐づける。
     */
    public function forCertification(Certification $certification): static
    {
        return $this->state(fn () => [
            'certification_id' => $certification->id,
        ]);
    }

    /**
     * 指定した投稿者に紐づける。
     */
    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
        ]);
    }
}
