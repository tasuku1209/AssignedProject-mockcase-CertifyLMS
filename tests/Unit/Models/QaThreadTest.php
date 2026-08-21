<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * QaThread モデルのリレーション・Cast を検証する Unit テスト。
 *
 * 3 リレーション (user / certification / replies) +
 * 2 cast (status / resolved_at) を網羅する。
 */
class QaThreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relation_returns_posting_user(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $thread = QaThread::factory()
            ->forUser($user)
            ->create();

        // Act
        $parent = $thread->user;

        // Assert
        $this->assertTrue($parent->is($user));
    }

    public function test_certification_relation_returns_attached_certification(): void
    {
        // Arrange
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()
            ->forCertification($certification)
            ->create();

        // Act
        $parent = $thread->certification;

        // Assert
        $this->assertTrue($parent->is($certification));
    }

    public function test_replies_relation_returns_attached_replies(): void
    {
        // Arrange
        $thread = QaThread::factory()->create();

        QaReply::factory()
            ->forThread($thread)
            ->create();

        QaReply::factory()
            ->forThread($thread)
            ->create();

        QaReply::factory()->create();

        // Act
        $replies = $thread->replies;

        // Assert
        $this->assertCount(
            2,
            $replies,
            '対象スレッドの回答のみが取得されるはず'
        );
    }

    public function test_status_cast_converts_to_enum(): void
    {
        // Arrange
        $thread = QaThread::factory()
            ->unresolved()
            ->create();

        // Act
        $fresh = $thread->fresh();

        // Assert
        $this->assertInstanceOf(QaThreadStatus::class, $fresh->status);
        $this->assertSame(QaThreadStatus::Unresolved, $fresh->status);
    }

    public function test_resolved_at_cast_returns_carbon(): void
    {
        // Arrange
        $thread = QaThread::factory()
            ->resolved()
            ->create();

        // Act
        $fresh = $thread->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->resolved_at);
    }
}
