<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChatMessage;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 正常なメッセージ送信に使用するConversationを作成する。
     */
    private function createConversation(User $student): AiChatConversation
    {
        $certification = Certification::factory()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();

        return AiChatConversation::factory()
            ->forEnrollment($enrollment)
            ->create();
    }

    public function test_student_can_send_message_and_receive_json_response(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => "TITLE:\n学習内容について\n\nANSWER:\nこれは回答です。",
                                ],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 10,
                    'candidatesTokenCount' => 20,
                ],
            ]),
        ]);

        $student = User::factory()->student()->create();
        $conversation = $this->createConversation($student);

        $response = $this->actingAs($student)
            ->postJson(
                route(
                    'ai-chat.conversations.messages.store',
                    $conversation,
                ),
                [
                    'content' => 'これは質問です。',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'user_message',
                'assistant_message',
                'conversation',
            ]);

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'role' => 'user',
            'status' => 'completed',
            'content' => 'これは質問です。',
        ]);

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'status' => 'completed',
        ]);
    }

    public function test_message_content_is_validated(): void
    {
        $student = User::factory()->student()->create();
        $conversation = $this->createConversation($student);

        $response = $this->actingAs($student)
            ->postJson(
                route(
                    'ai-chat.conversations.messages.store',
                    $conversation,
                ),
                [
                    'content' => '',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');
    }

    public function test_other_student_cannot_send_message(): void
    {
        $owner = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $conversation = $this->createConversation($owner);

        $response = $this->actingAs($otherStudent)
            ->postJson(
                route(
                    'ai-chat.conversations.messages.store',
                    $conversation,
                ),
                [
                    'content' => '他人の相談への質問です。',
                ],
            );

        $response->assertForbidden();

        $this->assertDatabaseMissing('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'content' => '他人の相談への質問です。',
        ]);
    }

    public function test_message_sending_is_rejected_when_daily_limit_is_reached(): void
    {
        Http::fake();

        $student = User::factory()->student()->create();
        $conversation = $this->createConversation($student);

        AiChatMessage::factory()
            ->assistant()
            ->forConversation($conversation)
            ->count(config('ai-chat.daily_limit'))
            ->create();

        $beforeCount = AiChatMessage::query()
            ->where('ai_chat_conversation_id', $conversation->id)
            ->count();

        $response = $this->actingAs($student)
            ->postJson(
                route(
                    'ai-chat.conversations.messages.store',
                    $conversation,
                ),
                [
                    'content' => '上限到達後の質問です。',
                ],
            );

        $response
            ->assertStatus(429);

        $this->assertSame(
            $beforeCount,
            AiChatMessage::query()
                ->where('ai_chat_conversation_id', $conversation->id)
                ->count(),
        );

        Http::assertNothingSent();
    }
}
