<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * AiChatConversation モデルのリレーション・Cast を検証する Unit テスト。
 * 4 リレーション (user / enrollment / section / messages)
 * + 2 cast (auto_title_enabled / last_message_at) を検証する。
 */
class AiChatConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relation_returns_parent_user(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()
            ->forUser($user)
            ->create();

        // Act
        $parent = $conversation->user;

        // Assert
        $this->assertTrue($parent->is($user));
    }

    public function test_enrollment_relation_returns_parent_enrollment(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->learning()->create();
        $conversation = AiChatConversation::factory()
            ->forEnrollment($enrollment)
            ->create();

        // Act
        $parent = $conversation->enrollment;

        // Assert
        $this->assertTrue($parent->is($enrollment));
    }

    public function test_section_relation_returns_parent_section(): void
    {
        // Arrange
        $section = Section::factory()->create();
        $conversation = AiChatConversation::factory()
            ->forSection($section)
            ->create();

        // Act
        $parent = $conversation->section;

        // Assert
        $this->assertTrue($parent->is($section));
    }

    public function test_messages_relation_returns_attached_messages(): void
    {
        // Arrange
        $conversation = AiChatConversation::factory()->create();

        AiChatMessage::factory()
            ->user()
            ->forConversation($conversation)
            ->create();

        AiChatMessage::factory()
            ->assistant()
            ->forConversation($conversation)
            ->create();

        AiChatMessage::factory()
            ->user()
            ->create();

        // Act
        $messages = $conversation->messages;

        // Assert
        $this->assertCount(2, $messages);
    }

    public function test_auto_title_enabled_cast_returns_boolean(): void
    {
        // Arrange
        $conversation = AiChatConversation::factory()
            ->state([
                'auto_title_enabled' => true,
            ])
            ->create();

        // Act
        $fresh = $conversation->fresh();

        // Assert
        $this->assertIsBool(
            $fresh->auto_title_enabled,
            'auto_title_enabled は boolean にキャストされるはず',
        );
        $this->assertTrue($fresh->auto_title_enabled);
    }

    public function test_last_message_at_cast_returns_carbon(): void
    {
        // Arrange
        $conversation = AiChatConversation::factory()
            ->state([
                'last_message_at' => now(),
            ])
            ->create();

        // Act
        $fresh = $conversation->fresh();

        // Assert
        $this->assertInstanceOf(
            Carbon::class,
            $fresh->last_message_at,
            'last_message_at は Carbon にキャストされるはず',
        );
    }
}
