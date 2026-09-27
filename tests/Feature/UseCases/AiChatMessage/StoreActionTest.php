<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\AiChatMessage;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Services\GeminiService;
use App\UseCases\AiChatMessage\StoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

/**
 * AiChatMessage\StoreAction の責務:
 *
 * - 日次利用上限の確認
 * - User / Assistant メッセージの登録
 * - Gemini APIによる回答生成
 * - Assistantメッセージへの結果保存
 * - Conversationのタイトル・最終メッセージ日時更新
 * - Geminiエラー時のAssistantメッセージをErrorへ更新
 */
#[Group('external-api')]
class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_message_and_save_gemini_response(): void
    {
        $student = User::factory()->student()->create();

        $conversation = AiChatConversation::factory()
            ->forUser($student)
            ->create([
                'title' => '現在のタイトル',
                'auto_title_enabled' => true,
                'last_message_at' => null,
            ]);

        $gemini = Mockery::mock(GeminiService::class);

        $gemini->shouldReceive('generate')
            ->once()
            ->andReturn([
                'text' => 'AIからの回答です。',
                'title' => '新しいAI相談タイトル',
                'model' => 'gemini-3.1-flash-lite',
                'input_tokens' => 100,
                'output_tokens' => 50,
            ]);

        $this->app->instance(GeminiService::class, $gemini);

        $result = app(StoreAction::class)(
            $conversation,
            $student,
            [
                'content' => 'これは質問です。',
            ],
        );

        $this->assertDatabaseHas('ai_chat_messages', [
            'id' => $result['user_message']->id,
            'ai_chat_conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::User->value,
            'status' => AiChatMessageStatus::Completed->value,
            'content' => 'これは質問です。',
        ]);

        $this->assertDatabaseHas('ai_chat_messages', [
            'id' => $result['assistant_message']->id,
            'ai_chat_conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::Assistant->value,
            'status' => AiChatMessageStatus::Completed->value,
            'content' => 'AIからの回答です。',
            'model' => 'gemini-3.1-flash-lite',
            'input_tokens' => 100,
            'output_tokens' => 50,
        ]);

        $conversation->refresh();

        $this->assertSame(
            '新しいAI相談タイトル',
            $conversation->title,
        );
        $this->assertNotNull($conversation->last_message_at);

        $this->assertSame(
            'AIからの回答です。',
            $result['assistant_message']->content,
        );
    }

    public function test_manual_title_is_not_overwritten(): void
    {
        $student = User::factory()->student()->create();

        $conversation = AiChatConversation::factory()
            ->forUser($student)
            ->create([
                'title' => '手動で設定したタイトル',
                'auto_title_enabled' => false,
            ]);

        $gemini = Mockery::mock(GeminiService::class);

        $gemini->shouldReceive('generate')
            ->once()
            ->andReturn([
                'text' => 'AIからの回答です。',
                'title' => 'Geminiが生成したタイトル',
                'model' => 'gemini-3.1-flash-lite',
                'input_tokens' => 100,
                'output_tokens' => 50,
            ]);

        $this->app->instance(GeminiService::class, $gemini);

        app(StoreAction::class)(
            $conversation,
            $student,
            [
                'content' => '質問です。',
            ],
        );

        $conversation->refresh();

        $this->assertSame(
            '手動で設定したタイトル',
            $conversation->title,
        );

        $this->assertFalse($conversation->auto_title_enabled);
        $this->assertNotNull($conversation->last_message_at);
    }

    public function test_daily_limit_prevents_gemini_call_and_message_creation(): void
    {
        $student = User::factory()->student()->create();

        $conversation = AiChatConversation::factory()
            ->forUser($student)
            ->create();

        AiChatMessage::factory()
            ->assistant()
            ->forConversation($conversation)
            ->count(config('ai-chat.daily_limit'))
            ->create();

        $beforeCount = AiChatMessage::query()
            ->whereHas('conversation', function ($query) use ($student): void {
                $query->where('user_id', $student->id);
            })
            ->count();

        $gemini = Mockery::mock(GeminiService::class);

        $gemini->shouldNotReceive('generate');

        $this->app->instance(GeminiService::class, $gemini);

        $this->expectException(
            TooManyRequestsHttpException::class,
        );

        try {
            app(StoreAction::class)(
                $conversation,
                $student,
                [
                    'content' => '上限到達後の質問です。',
                ],
            );
        } finally {
            $afterCount = AiChatMessage::query()
                ->whereHas('conversation', function ($query) use ($student): void {
                    $query->where('user_id', $student->id);
                })
                ->count();

            $this->assertSame($beforeCount, $afterCount);
        }
    }

    public function test_gemini_error_changes_assistant_message_to_error(): void
    {
        $student = User::factory()->student()->create();

        $conversation = AiChatConversation::factory()
            ->forUser($student)
            ->create([
                'last_message_at' => null,
            ]);

        $gemini = Mockery::mock(GeminiService::class);

        $gemini->shouldReceive('generate')
            ->once()
            ->andThrow(new \RuntimeException('Gemini API error'));

        $this->app->instance(GeminiService::class, $gemini);

        try {
            app(StoreAction::class)(
                $conversation,
                $student,
                [
                    'content' => '質問です。',
                ],
            );

            $this->fail('RuntimeExceptionが発生することを期待しました。');
        } catch (\RuntimeException $e) {
            $this->assertSame('Gemini API error', $e->getMessage());
        }

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::User->value,
            'status' => AiChatMessageStatus::Completed->value,
            'content' => '質問です。',
        ]);

        $assistantMessage = AiChatMessage::query()
            ->where('ai_chat_conversation_id', $conversation->id)
            ->where('role', AiChatMessageRole::Assistant)
            ->first();

        $this->assertNotNull($assistantMessage);
        $this->assertSame(
            AiChatMessageStatus::Error,
            $assistantMessage->status,
        );
        $this->assertSame(
            'Gemini API error',
            $assistantMessage->error_detail,
        );
        $this->assertNotNull($assistantMessage->response_time_ms);

        $conversation->refresh();

        $this->assertNull($conversation->last_message_at);
    }

    public function test_prompt_contains_history_title_and_current_question(): void
    {
        $student = User::factory()->student()->create();

        $conversation = AiChatConversation::factory()
            ->forUser($student)
            ->create([
                'title' => '既存の相談タイトル',
            ]);

        AiChatMessage::factory()
            ->user()
            ->forConversation($conversation)
            ->create([
                'content' => '以前の質問です。',
            ]);

        AiChatMessage::factory()
            ->assistant()
            ->forConversation($conversation)
            ->create([
                'content' => '以前のAI回答です。',
            ]);

        $gemini = Mockery::mock(GeminiService::class);

        $gemini->shouldReceive('generate')
            ->once()
            ->with(Mockery::on(function (string $prompt): bool {
                return str_contains($prompt, '既存の相談タイトル')
                    && str_contains($prompt, '以前の質問です。')
                    && str_contains($prompt, '以前のAI回答です。')
                    && str_contains($prompt, '今回の質問です。')
                    && str_contains($prompt, 'TITLE:')
                    && str_contains($prompt, 'ANSWER:');
            }))
            ->andReturn([
                'text' => 'AIからの回答です。',
                'title' => '相談タイトル',
                'model' => 'gemini-3.1-flash-lite',
                'input_tokens' => 100,
                'output_tokens' => 50,
            ]);

        $this->app->instance(GeminiService::class, $gemini);

        app(StoreAction::class)(
            $conversation,
            $student,
            [
                'content' => '今回の質問です。',
            ],
        );
    }

    public function test_auto_title_is_not_updated_when_auto_title_setting_is_disabled(): void
    {
        // Arrange
        config([
            'ai-chat.auto_title.enabled' => false,
        ]);

        $student = User::factory()->student()->create();

        $conversation = AiChatConversation::factory()
            ->for($student)
            ->create([
                'title' => '新規相談',
                'auto_title_enabled' => false,
            ]);

        $this->mock(GeminiService::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => 'AIからの回答です。',
                    'title' => 'AIが生成した新しいタイトル',
                    'model' => 'gemini-test-model',
                    'input_tokens' => 100,
                    'output_tokens' => 200,
                ]);
        });

        $action = app(StoreAction::class);

        // Act
        $result = $action(
            $conversation,
            $student,
            [
                'content' => 'テスト用の質問です。',
            ],
        );

        // Assert
        $this->assertSame(
            '新規相談',
            $result['conversation']->title,
        );

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
            'title' => '新規相談',
            'auto_title_enabled' => false,
        ]);
    }

    public function test_signature_is_conversation_user_array(): void
    {
        $reflection = new \ReflectionMethod(
            StoreAction::class,
            '__invoke',
        );

        $params = $reflection->getParameters();

        $this->assertCount(3, $params);
        $this->assertSame(
            AiChatConversation::class,
            $params[0]->getType()?->getName(),
        );
        $this->assertSame(
            User::class,
            $params[1]->getType()?->getName(),
        );
        $this->assertSame(
            'array',
            $params[2]->getType()?->getName(),
        );
    }
}
