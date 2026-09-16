<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChatConversation;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_delete_conversation_and_its_messages(): void
    {
        $student = User::factory()->student()->create();

        $conversation = AiChatConversation::factory()
            ->forUser($student)
            ->create();

        $message = AiChatMessage::factory()
            ->forConversation($conversation)
            ->create();

        $response = $this->actingAs($student)->delete(
            route('ai-chat.conversations.destroy', $conversation),
        );

        $response->assertRedirect(route('ai-chat.index'));

        $response->assertSessionHas(
            'success',
            'AI相談を削除しました。',
        );

        $this->assertDatabaseMissing('ai_chat_conversations', [
            'id' => $conversation->id,
        ]);

        $this->assertDatabaseMissing('ai_chat_messages', [
            'id' => $message->id,
        ]);
    }

    public function test_other_student_cannot_delete_conversation(): void
    {
        $owner = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $conversation = AiChatConversation::factory()
            ->forUser($owner)
            ->create();

        $message = AiChatMessage::factory()
            ->forConversation($conversation)
            ->create();

        $response = $this->actingAs($otherStudent)->delete(
            route('ai-chat.conversations.destroy', $conversation),
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
        ]);

        $this->assertDatabaseHas('ai_chat_messages', [
            'id' => $message->id,
        ]);
    }
}
