<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QaReply モデルのリレーションを検証する Unit テスト。
 *
 * 2 リレーション (user / qaThread) を網羅する。
 */
class QaReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relation_returns_replying_user(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $reply = QaReply::factory()
            ->forUser($user)
            ->create();

        // Act
        $parent = $reply->user;

        // Assert
        $this->assertTrue($parent->is($user));
    }

    public function test_qa_thread_relation_returns_attached_thread(): void
    {
        // Arrange
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()
            ->forThread($thread)
            ->create();

        // Act
        $parent = $reply->qaThread;

        // Assert
        $this->assertTrue($parent->is($thread));
    }
}
