<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChatConversation;

use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_update_conversation_title(): void
    {
        $student = User::factory()->student()->create();

        $conversation = AiChatConversation::factory()
            ->forUser($student)
            ->autoTitleDisabled()
            ->create([
                'title' => '変更前のタイトル',
            ]);

        $conversation->update([
            'auto_title_enabled' => true,
        ]);

        $response = $this->actingAs($student)->patch(
            route('ai-chat.conversations.update', $conversation),
            [
                'title' => '変更後のタイトル',
            ],
        );

        $response->assertRedirect(
            route('ai-chat.conversations.show', $conversation),
        );

        $response->assertSessionHas(
            'success',
            'AI相談のタイトルを更新しました。',
        );

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
            'title' => '変更後のタイトル',
            'auto_title_enabled' => false,
        ]);
    }

    public function test_title_validation_error_does_not_update_conversation(): void
    {
        $student = User::factory()->student()->create();

        $conversation = AiChatConversation::factory()
            ->forUser($student)
            ->create([
                'title' => '変更前のタイトル',
                'auto_title_enabled' => true,
            ]);

        $response = $this->actingAs($student)->patch(
            route('ai-chat.conversations.update', $conversation),
            [
                'title' => '',
            ],
        );

        $response->assertSessionHasErrors('title');

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
            'title' => '変更前のタイトル',
            'auto_title_enabled' => true,
        ]);
    }

    public function test_other_student_cannot_update_conversation(): void
    {
        $owner = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $conversation = AiChatConversation::factory()
            ->forUser($owner)
            ->create([
                'title' => '変更前のタイトル',
                'auto_title_enabled' => true,
            ]);

        $response = $this->actingAs($otherStudent)->patch(
            route('ai-chat.conversations.update', $conversation),
            [
                'title' => '不正な変更',
            ],
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
            'title' => '変更前のタイトル',
            'auto_title_enabled' => true,
        ]);
    }
}
