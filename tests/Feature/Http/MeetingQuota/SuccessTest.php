<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingQuota;

use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_success_page_with_payment(): void
    {
        $student = User::factory()->student()->create();

        $meetingPack = MeetingPack::factory()
            ->published()
            ->create([
                'name' => '5回パック',
                'meeting_count' => 5,
                'price' => 12000,
            ]);

        $payment = Payment::factory()
            ->for($student)
            ->forMeetingPack($meetingPack)
            ->succeeded()
            ->create([
                'stripe_checkout_session_id' => 'cs_test_123',
            ]);

        $response = $this->actingAs($student)
            ->get(route('meeting-quota.success', [
                'session_id' => 'cs_test_123',
            ]));

        $response->assertOk();
        $response->assertViewIs('meeting-quota.success');

        $viewPayment = $response->viewData('payment');

        $this->assertNotNull($viewPayment);
        $this->assertTrue(
            $viewPayment->is($payment),
            '指定されたStripe Checkout Sessionに対応するPaymentが取得されるはず',
        );

        $this->assertTrue(
            $viewPayment->relationLoaded('meetingPack'),
            'meetingPackはEager Loadされているはず',
        );

        $this->assertTrue(
            $viewPayment->meetingPack->is($meetingPack),
            'Paymentに紐づくMeetingPackが取得されるはず',
        );
    }

    public function test_payment_is_not_retrieved_when_session_id_does_not_match(): void
    {
        $student = User::factory()->student()->create();

        $meetingPack = MeetingPack::factory()
            ->published()
            ->create();

        Payment::factory()
            ->for($student)
            ->forMeetingPack($meetingPack)
            ->succeeded()
            ->create([
                'stripe_checkout_session_id' => 'cs_test_123',
            ]);

        $response = $this->actingAs($student)
            ->get(route('meeting-quota.success', [
                'session_id' => 'cs_test_other',
            ]));

        $response->assertOk();

        $this->assertNull(
            $response->viewData('payment'),
            'session_idが一致しないPaymentは取得されないはず',
        );
    }

    public function test_payment_belonging_to_another_user_is_not_retrieved(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $meetingPack = MeetingPack::factory()
            ->published()
            ->create();

        Payment::factory()
            ->for($otherStudent)
            ->forMeetingPack($meetingPack)
            ->succeeded()
            ->create([
                'stripe_checkout_session_id' => 'cs_test_123',
            ]);

        $response = $this->actingAs($student)
            ->get(route('meeting-quota.success', [
                'session_id' => 'cs_test_123',
            ]));

        $response->assertOk();

        $this->assertNull(
            $response->viewData('payment'),
            '他ユーザーのPaymentは取得されないはず',
        );
    }

    public function test_coach_cannot_view_success_page(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)
            ->get(route('meeting-quota.success', [
                'session_id' => 'cs_test_123',
            ]))
            ->assertForbidden();
    }
}
