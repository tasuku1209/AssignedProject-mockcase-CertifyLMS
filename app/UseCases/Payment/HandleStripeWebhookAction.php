<?php

declare(strict_types=1);

namespace App\UseCases\Payment;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\StripeWebhookEvent;
use Illuminate\Support\Facades\DB;
use Stripe\Event;
use Stripe\Webhook;

/**
 * Stripe Webhook を処理する Action。
 */
final class HandleStripeWebhookAction
{
    /**
     * Stripe Webhook を処理する。
     *
     * @param string $payload Stripe から送信された JSON
     * @param string|null $signature Stripe-Signature ヘッダー
     */
    public function execute(string $payload, ?string $signature): void
    {
        $event = Webhook::constructEvent(
            $payload,
            $signature ?? '',
            config('services.stripe.webhook_secret'),
        );

        DB::transaction(function () use ($event): void {
            $webhookEvent = StripeWebhookEvent::query()
                ->where('stripe_event_id', $event->id)
                ->lockForUpdate()
                ->first();

            if ($webhookEvent !== null) {
                return;
            }

            $webhookEvent = StripeWebhookEvent::create([
                'stripe_event_id' => $event->id,
                'event_type' => $event->type,
            ]);

            if ($event->type === 'checkout.session.completed') {
                $this->handleCheckoutSessionCompleted($event);
            }

            $webhookEvent->update([
                'processed_at' => now(),
            ]);
        });
    }

    /**
     * Checkout Session 完了時の決済を確定する。
     */
    private function handleCheckoutSessionCompleted(Event $event): void
    {
        $session = $event->data->object;

        if ($session->payment_status !== 'paid') {
            return;
        }

        $paymentId = $session->metadata->payment_id ?? null;

        if (! is_string($paymentId) || $paymentId === '') {
            return;
        }

        $payment = Payment::query()
            ->whereKey($paymentId)
            ->lockForUpdate()
            ->first();

        if ($payment === null) {
            return;
        }

        if ($payment->status === PaymentStatus::Succeeded) {
            return;
        }

        $payment->update([
            'status' => PaymentStatus::Succeeded,
            'paid_at' => now(),
        ]);

        MeetingQuotaTransaction::create([
            'user_id' => $payment->user_id,
            'type' => MeetingQuotaTransactionType::Purchased,
            'amount' => $payment->quantity,
            'related_payment_id' => $payment->id,
            'occurred_at' => now(),
        ]);
    }
}
