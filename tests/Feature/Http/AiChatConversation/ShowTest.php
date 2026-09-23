<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChatConversation;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AIチャット相談詳細 Show の Feature テスト。
 * HTML / JSON の正常表示、必要な関連データのロード、認可を検証する。
 */
class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_conversation_as_html(): void
    {
        $student = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()
            ->forUser($student)
            ->create();

        $response = $this->actingAs($student)
            ->get(route('ai-chat.conversations.show', $conversation));

        $response->assertOk();
        $response->assertViewIs('ai-chat.show');
        $response->assertViewHas('conversation', $conversation);
    }

    public function test_student_can_view_conversation_as_json(): void
    {
        $student = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()
            ->forUser($student)
            ->create();

        $message = AiChatMessage::factory()
            ->forConversation($conversation)
            ->user()
            ->create();

        $response = $this->actingAs($student)
            ->getJson(route('ai-chat.conversations.show', $conversation));

        $response->assertOk();
        $response->assertJsonPath(
            'conversation.id',
            $conversation->id,
        );
        $response->assertJsonPath(
            'messages.0.id',
            $message->id,
        );
    }

    public function test_show_loads_required_relations(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $section = Section::factory()->create();

        $conversation = AiChatConversation::factory()
            ->forUser($student)
            ->create([
                'enrollment_id' => $enrollment->id,
                'section_id' => $section->id,
            ]);

        AiChatMessage::factory()
            ->forConversation($conversation)
            ->user()
            ->create();

        $response = $this->actingAs($student)
            ->get(route('ai-chat.conversations.show', $conversation));

        $response->assertOk();

        $loadedConversation = $response->viewData('conversation');

        $this->assertTrue(
            $loadedConversation->relationLoaded('messages'),
        );
        $this->assertTrue(
            $loadedConversation->relationLoaded('enrollment'),
        );
        $this->assertTrue(
            $loadedConversation->enrollment->relationLoaded('certification'),
        );
        $this->assertTrue(
            $loadedConversation->relationLoaded('section'),
        );
    }

    public function test_other_student_cannot_view_conversation(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $conversation = AiChatConversation::factory()
            ->forUser($student)
            ->create();

        $this->actingAs($otherStudent)
            ->get(route('ai-chat.conversations.show', $conversation))
            ->assertForbidden();
    }
}
