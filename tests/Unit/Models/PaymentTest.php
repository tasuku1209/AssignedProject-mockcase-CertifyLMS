<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Payment モデルのリレーション・Cast を検証する Unit テスト。
 *
 * 主要 3 リレーション (user / meetingPack / quotaTransactions) +
 * 4 cast (quantity / amount / status / paid_at) を網羅する。
 */
class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relation_returns_purchasing_user(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $payment = Payment::factory()
            ->for($user)
            ->create();

        // Act
        $relatedUser = $payment->user;

        // Assert
        $this->assertTrue($relatedUser->is($user));
    }

    public function test_meeting_pack_relation_returns_purchased_pack(): void
    {
        // Arrange
        $meetingPack = MeetingPack::factory()
            ->published()
            ->create();

        $payment = Payment::factory()
            ->forMeetingPack($meetingPack)
            ->create();

        // Act
        $relatedMeetingPack = $payment->meetingPack;

        // Assert
        $this->assertTrue($relatedMeetingPack->is($meetingPack));
    }

    public function test_quota_transactions_relation_returns_related_transactions(): void
    {
        // Arrange
        $payment = Payment::factory()->create();

        MeetingQuotaTransaction::factory()
            ->purchased(5, $payment->id)
            ->create([
                'user_id' => $payment->user_id,
            ]);

        MeetingQuotaTransaction::factory()
            ->purchased(10, $payment->id)
            ->create([
                'user_id' => $payment->user_id,
            ]);

        MeetingQuotaTransaction::factory()
            ->purchased(1)
            ->create([
                'user_id' => $payment->user_id,
            ]);

        // Act
        $transactions = $payment->quotaTransactions;

        // Assert
        $this->assertCount(
            2,
            $transactions,
            '対象 Payment に関連する購入トランザクションのみ取得されるはず',
        );
    }

    public function test_quantity_and_amount_casts_return_integer(): void
    {
        // Arrange
        $payment = Payment::factory()->create([
            'quantity' => 10,
            'amount' => 21000,
        ]);

        // Act
        $fresh = $payment->fresh();

        // Assert
        $this->assertIsInt($fresh->quantity);
        $this->assertSame(10, $fresh->quantity);

        $this->assertIsInt($fresh->amount);
        $this->assertSame(21000, $fresh->amount);
    }

    public function test_status_cast_returns_payment_status_enum(): void
    {
        // Arrange
        $payment = Payment::factory()->succeeded()->create();

        // Act
        $fresh = $payment->fresh();

        // Assert
        $this->assertInstanceOf(
            PaymentStatus::class,
            $fresh->status,
            'status は PaymentStatus enum にキャストされるはず',
        );
        $this->assertSame(PaymentStatus::Succeeded, $fresh->status);
    }

    public function test_paid_at_cast_returns_carbon(): void
    {
        // Arrange
        $payment = Payment::factory()->succeeded()->create();

        // Act
        $fresh = $payment->fresh();

        // Assert
        $this->assertInstanceOf(
            Carbon::class,
            $fresh->paid_at,
            'paid_at は Carbon にキャストされるはず',
        );
    }
}
