<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\MeetingQuotaPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MeetingQuotaPolicy の判定を検証する Unit テスト。
 * viewHistory は本人のみ (auth.id === target.id) を網羅する。
 * viewCheckout / createCheckout / viewSuccess は受講生のみ (role === Student) を網羅する。
 */
class MeetingQuotaPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_history_allowed_only_for_self(): void
    {
        // Arrange
        $self = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $policy = new MeetingQuotaPolicy;

        // Act & Assert
        $this->assertTrue($policy->viewHistory($self, $self), '本人は自分の履歴を閲覧可');
        $this->assertFalse($policy->viewHistory($self, $other), '他人の履歴は閲覧不可');
    }

    public function test_view_checkout_allowed_only_for_student(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $policy = new MeetingQuotaPolicy;

        // Act & Assert
        $this->assertTrue(
            $policy->viewCheckout($student),
            'Student は追加面談購入画面を閲覧可',
        );

        $this->assertFalse(
            $policy->viewCheckout($coach),
            'Coach は追加面談購入画面を閲覧不可',
        );

        $this->assertFalse(
            $policy->viewCheckout($admin),
            'Admin は追加面談購入画面を閲覧不可',
        );
    }

    public function test_create_checkout_allowed_only_for_student(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $policy = new MeetingQuotaPolicy;

        // Act & Assert
        $this->assertTrue(
            $policy->createCheckout($student),
            'Student は追加面談購入を開始可',
        );

        $this->assertFalse(
            $policy->createCheckout($coach),
            'Coach は追加面談購入を開始不可',
        );

        $this->assertFalse(
            $policy->createCheckout($admin),
            'Admin は追加面談購入を開始不可',
        );
    }

    public function test_view_success_allowed_only_for_student(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $policy = new MeetingQuotaPolicy;

        // Act & Assert
        $this->assertTrue(
            $policy->viewSuccess($student),
            'Student は購入完了画面を閲覧可',
        );

        $this->assertFalse(
            $policy->viewSuccess($coach),
            'Coach は購入完了画面を閲覧不可',
        );

        $this->assertFalse(
            $policy->viewSuccess($admin),
            'Admin は購入完了画面を閲覧不可',
        );
    }
}
