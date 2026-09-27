<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingQuota;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Stripe\Service\Checkout\SessionService;
use Stripe\StripeClient;
use Tests\TestCase;

#[Group('external-api')]
class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_checkout_session(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $meetingPack = MeetingPack::factory()
            ->published()
            ->create([
                'meeting_count' => 5,
                'price' => 12000,
                'stripe_price_id' => 'price_test_5',
            ]);

        $session = (object) [
            'id' => 'cs_test_123',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
        ];

        $sessions = Mockery::mock(SessionService::class);
        $sessions->shouldReceive('create')
            ->once()
            ->andReturn($session);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->checkout = Mockery::mock();
        $stripe->checkout->sessions = $sessions;

        $this->app->instance(StripeClient::class, $stripe);

        // Act
        $response = $this->actingAs($student)
            ->post(route('meeting-quota.checkout.create'), [
                'meeting_pack_id' => $meetingPack->id,
            ]);

        // Assert
        $response->assertRedirect($session->url);

        $this->assertDatabaseHas('payments', [
            'user_id' => $student->id,
            'meeting_pack_id' => $meetingPack->id,
            'stripe_checkout_session_id' => $session->id,
            'quantity' => 5,
            'amount' => 12000,
            'status' => PaymentStatus::Pending->value,
        ]);
    }

    public function test_stripe_checkout_failure_marks_payment_as_failed(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $meetingPack = MeetingPack::factory()
            ->published()
            ->create([
                'meeting_count' => 5,
                'price' => 12000,
                'stripe_price_id' => 'price_test_5',
            ]);

        $sessions = Mockery::mock(SessionService::class);
        $sessions->shouldReceive('create')
            ->once()
            ->andThrow(new \RuntimeException('Stripe API error'));

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->checkout = Mockery::mock();
        $stripe->checkout->sessions = $sessions;

        $this->app->instance(StripeClient::class, $stripe);

        // Act
        $response = $this->actingAs($student)
            ->post(route('meeting-quota.checkout.create'), [
                'meeting_pack_id' => $meetingPack->id,
            ]);

        // Assert
        $response->assertStatus(500);

        $this->assertDatabaseHas('payments', [
            'user_id' => $student->id,
            'meeting_pack_id' => $meetingPack->id,
            'stripe_checkout_session_id' => null,
            'quantity' => 5,
            'amount' => 12000,
            'status' => PaymentStatus::Failed->value,
        ]);
    }

    public function test_store_fails_when_meeting_pack_id_is_invalid(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act
        $response = $this->actingAs($student)
            ->post(route('meeting-quota.checkout.create'), [
                'meeting_pack_id' => 'invalid-id',
            ]);

        // Assert
        $response->assertSessionHasErrors('meeting_pack_id');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_coach_cannot_create_checkout_session(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        $meetingPack = MeetingPack::factory()
            ->published()
            ->create([
                'stripe_price_id' => 'price_test_1',
            ]);

        // Act & Assert
        $this->actingAs($coach)
            ->post(route('meeting-quota.checkout.create'), [
                'meeting_pack_id' => $meetingPack->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
    }
}
