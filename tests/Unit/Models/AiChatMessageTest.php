<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AiChatMessage モデルのリレーション・Cast を検証する Unit テスト。
 * 1 リレーション (conversation)
 * + 2 cast (role / status) を検証する。
 */
class AiChatMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_relation_returns_parent_conversation(): void
    {
        // Arrange
        $conversation = AiChatConversation::factory()->create();
        $message = AiChatMessage::factory()
            ->forConversation($conversation)
            ->create();

        // Act
        $parent = $message->conversation;

        // Assert
        $this->assertTrue($parent->is($conversation));
    }

    public function test_role_cast_returns_enum(): void
    {
        // Arrange
        $message = AiChatMessage::factory()
            ->user()
            ->create();

        // Act
        $fresh = $message->fresh();

        // Assert
        $this->assertInstanceOf(
            AiChatMessageRole::class,
            $fresh->role,
            'role は AiChatMessageRole enum にキャストされるはず',
        );
        $this->assertSame(
            AiChatMessageRole::User,
            $fresh->role,
        );
    }

    public function test_status_cast_returns_enum(): void
    {
        // Arrange
        $message = AiChatMessage::factory()
            ->assistant()
            ->create();

        // Act
        $fresh = $message->fresh();

        // Assert
        $this->assertInstanceOf(
            AiChatMessageStatus::class,
            $fresh->status,
            'status は AiChatMessageStatus enum にキャストされるはず',
        );
        $this->assertSame(
            AiChatMessageStatus::Completed,
            $fresh->status,
        );
    }
}
