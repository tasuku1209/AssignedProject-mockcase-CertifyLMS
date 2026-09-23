<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Webhook;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.stripe.webhook_secret' => self::WEBHOOK_SECRET,
        ]);
    }

    public function test_paid_checkout_session_completed_succeeds_payment_and_adds_quota(): void
    {
        $student = User::factory()->student()->create();

        $meetingPack = MeetingPack::factory()
            ->published()
            ->create([
                'meeting_count' => 5,
                'price' => 12000,
            ]);

        $payment = Payment::factory()
            ->for($student)
            ->forMeetingPack($meetingPack)
            ->create([
                'quantity' => 5,
                'amount' => 12000,
                'status' => PaymentStatus::Pending,
            ]);

        $payload = $this->createCheckoutSessionPayload(
            eventId: 'evt_test_001',
            paymentId: $payment->id,
            paymentStatus: 'paid',
        );

        $response = $this->postSignedWebhook($payload['body'], $payload['signature']);

        $response->assertOk();
        $response->assertJson([
            'received' => true,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Succeeded->value,
        ]);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => 5,
            'related_payment_id' => $payment->id,
        ]);

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_test_001',
            'event_type' => 'checkout.session.completed',
        ]);

        $webhookEvent = StripeWebhookEvent::query()
            ->where('stripe_event_id', 'evt_test_001')
            ->first();

        $this->assertNotNull($webhookEvent);
        $this->assertNotNull($webhookEvent->processed_at);
    }

    public function test_same_webhook_event_does_not_add_quota_twice(): void
    {
        $student = User::factory()->student()->create();

        $meetingPack = MeetingPack::factory()
            ->published()
            ->create();

        $payment = Payment::factory()
            ->for($student)
            ->forMeetingPack($meetingPack)
            ->create([
                'quantity' => 5,
                'status' => PaymentStatus::Pending,
            ]);

        $payload = $this->createCheckoutSessionPayload(
            eventId: 'evt_test_duplicate',
            paymentId: $payment->id,
            paymentStatus: 'paid',
        );

        $this->postSignedWebhook(
            $payload['body'],
            $payload['signature'],
        )->assertOk();

        $this->postSignedWebhook(
            $payload['body'],
            $payload['signature'],
        )->assertOk();

        $this->assertDatabaseCount('meeting_quota_transactions', 1);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => 5,
            'related_payment_id' => $payment->id,
        ]);
    }

    public function test_checkout_session_completed_without_paid_status_does_not_update_payment(): void
    {
        $student = User::factory()->student()->create();

        $meetingPack = MeetingPack::factory()
            ->published()
            ->create();

        $payment = Payment::factory()
            ->for($student)
            ->forMeetingPack($meetingPack)
            ->create([
                'status' => PaymentStatus::Pending,
            ]);

        $payload = $this->createCheckoutSessionPayload(
            eventId: 'evt_test_unpaid',
            paymentId: $payment->id,
            paymentStatus: 'unpaid',
        );

        $this->postSignedWebhook(
            $payload['body'],
            $payload['signature'],
        )->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Pending->value,
            'paid_at' => null,
        ]);

        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_checkout_session_completed_without_payment_id_does_nothing(): void
    {
        $payload = $this->createCheckoutSessionPayload(
            eventId: 'evt_test_no_payment_id',
            paymentId: null,
            paymentStatus: 'paid',
        );

        $this->postSignedWebhook(
            $payload['body'],
            $payload['signature'],
        )->assertOk();

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_checkout_session_completed_with_nonexistent_payment_does_nothing(): void
    {
        $payload = $this->createCheckoutSessionPayload(
            eventId: 'evt_test_missing_payment',
            paymentId: '01KTESTNONEXISTENTPAYMENT',
            paymentStatus: 'paid',
        );

        $this->postSignedWebhook(
            $payload['body'],
            $payload['signature'],
        )->assertOk();

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_already_succeeded_payment_does_not_add_quota_twice(): void
    {
        $student = User::factory()->student()->create();

        $meetingPack = MeetingPack::factory()
            ->published()
            ->create();

        $payment = Payment::factory()
            ->for($student)
            ->forMeetingPack($meetingPack)
            ->succeeded()
            ->create([
                'quantity' => 5,
            ]);

        MeetingQuotaTransaction::factory()
            ->purchased($payment->quantity, $payment->id)
            ->state([
                'user_id' => $student->id,
            ])
            ->create();

        $payload = $this->createCheckoutSessionPayload(
            eventId: 'evt_test_already_succeeded',
            paymentId: $payment->id,
            paymentStatus: 'paid',
        );

        $this->postSignedWebhook(
            $payload['body'],
            $payload['signature'],
        )->assertOk();

        $this->assertDatabaseCount('meeting_quota_transactions', 1);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payload = $this->createCheckoutSessionPayload(
            eventId: 'evt_test_invalid_signature',
            paymentId: null,
            paymentStatus: 'paid',
        );

        $response = $this->postSignedWebhook(
            $payload['body'],
            'invalid-signature',
        );

        $response->assertStatus(500);

        $this->assertDatabaseCount('stripe_webhook_events', 0);
    }

    public function test_non_checkout_session_event_does_nothing(): void
    {
        $body = json_encode([
            'id' => 'evt_test_other_event',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_123',
                    'object' => 'payment_intent',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = $this->createSignature($body);

        $response = $this->postSignedWebhook($body, $signature);

        $response->assertOk();
        $response->assertJson([
            'received' => true,
        ]);

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_test_other_event',
            'event_type' => 'payment_intent.succeeded',
        ]);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    /**
     * checkout.session.completed のテスト用Payloadと署名を作成する。
     *
     * @return array{body: string, signature: string}
     */
    private function createCheckoutSessionPayload(
        string $eventId,
        ?string $paymentId,
        string $paymentStatus,
    ): array {
        $body = json_encode([
            'id' => $eventId,
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'object' => 'checkout.session',
                    'payment_status' => $paymentStatus,
                    'metadata' => $paymentId === null
                        ? []
                        : [
                            'payment_id' => $paymentId,
                        ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        return [
            'body' => $body,
            'signature' => $this->createSignature($body),
        ];
    }

    /**
     * Stripe Webhook用の署名を生成する。
     */
    private function createSignature(string $payload): string
    {
        $timestamp = time();

        $signedPayload = $timestamp.'.'.$payload;

        $signature = hash_hmac(
            'sha256',
            $signedPayload,
            self::WEBHOOK_SECRET,
        );

        return 't='.$timestamp.',v1='.$signature;
    }

    /**
     * raw JSONをそのままWebhookへ送信する。
     */
    private function postSignedWebhook(
        string $body,
        string $signature,
    ) {
        return $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $signature,
            ],
            $body,
        );
    }
}
