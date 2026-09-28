<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'meeting_pack_id' => MeetingPack::factory()
                ->published()
                ->withCount(1)
                ->withPrice(3000),
            'stripe_checkout_session_id' => null,
            'quantity' => 1,
            'amount' => 3000,
            'status' => PaymentStatus::Pending->value,
            'paid_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Pending->value,
            'paid_at' => null,
        ]);
    }

    public function succeeded(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Succeeded->value,
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Failed->value,
            'paid_at' => null,
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Refunded->value,
        ]);
    }

    public function forMeetingPack(MeetingPack $meetingPack): static
    {
        return $this->state(fn () => [
            'meeting_pack_id' => $meetingPack->id,
            'quantity' => $meetingPack->meeting_count,
            'amount' => $meetingPack->price,
        ]);
    }
}
