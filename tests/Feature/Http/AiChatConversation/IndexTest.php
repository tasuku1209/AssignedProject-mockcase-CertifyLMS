<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChatConversation;

use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AIチャット相談一覧 Index の Feature テスト。
 * Conversation の有無による表示・最新 Conversation の優先順位・認可を検証する。
 */
class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_empty_state_when_conversation_does_not_exist(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->get(route('ai-chat.index'));

        $response->assertOk();
        $response->assertViewIs('ai-chat.empty-state');
    }

    public function test_redirects_to_latest_conversation_with_priority_order(): void
    {
        $student = User::factory()->student()->create();

        $older = AiChatConversation::factory()
            ->forUser($student)
            ->create([
                'last_message_at' => now()->subDays(2),
                'created_at' => now()->subDays(2),
            ]);

        $newerCreated = AiChatConversation::factory()
            ->forUser($student)
            ->create([
                'last_message_at' => now()->subDays(2),
                'created_at' => now()->subDay(),
            ]);

        $latestMessage = AiChatConversation::factory()
            ->forUser($student)
            ->create([
                'last_message_at' => now(),
                'created_at' => now()->subDays(3),
            ]);

        $response = $this->actingAs($student)
            ->get(route('ai-chat.index'));

        $response->assertRedirect(
            route('ai-chat.conversations.show', $latestMessage),
        );

        $this->assertNotEquals($older->id, $latestMessage->id);
        $this->assertNotEquals($newerCreated->id, $latestMessage->id);
    }

    public function test_non_student_cannot_access_index(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)
            ->get(route('ai-chat.index'))
            ->assertForbidden();
    }
}
