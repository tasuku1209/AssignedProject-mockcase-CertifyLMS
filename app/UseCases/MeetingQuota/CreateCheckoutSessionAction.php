<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Stripe\StripeClient;

/**
 * 追加面談パック購入用の Stripe Checkout Session を作成する。
 */
final class CreateCheckoutSessionAction
{
    public function __construct(
        private readonly StripeClient $stripe,
    ) {}

    /**
     * Stripe Checkout Session を作成し、購入待ちの Payment を登録する。
     *
     * @param array{meeting_pack_id: string} $validated
     */
    public function __invoke(User $user, array $validated): string
    {
        $meetingPack = MeetingPack::query()
            ->published()
            ->whereKey($validated['meeting_pack_id'])
            ->firstOrFail();

        if ($meetingPack->stripe_price_id === null) {
            throw new RuntimeException(
                '購入可能な面談パックにStripe Price IDが設定されていません。'
            );
        }

        $payment = DB::transaction(function () use ($user, $meetingPack): Payment {
            return Payment::create([
                'user_id' => $user->id,
                'meeting_pack_id' => $meetingPack->id,
                'stripe_checkout_session_id' => null,
                'quantity' => $meetingPack->meeting_count,
                'amount' => $meetingPack->price,
                'status' => PaymentStatus::Pending,
            ]);
        });

        try {
            $session = $this->stripe->checkout->sessions->create([
                'mode' => 'payment',
                'line_items' => [
                    [
                        'price' => $meetingPack->stripe_price_id,
                        'quantity' => 1,
                    ],
                ],
                'success_url' => route('meeting-quota.success')
                    .'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('meeting-quota.checkout'),
                'metadata' => [
                    'payment_id' => $payment->id,
                ],
            ]);
        } catch (\Throwable $e) {
            $payment->update([
                'status' => PaymentStatus::Failed,
            ]);

            throw $e;
        }

        $payment->update([
            'stripe_checkout_session_id' => $session->id,
        ]);

        return $session->url;
    }
}
