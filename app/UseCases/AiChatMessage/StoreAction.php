<?php

declare(strict_types=1);

namespace App\UseCases\AiChatMessage;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * `final` 不採用: Mockery で mock するテストを想定するため。
 */
class StoreAction
{
    public function __construct(
        private readonly GeminiService $geminiService,
    ) {}

    /**
     * @param array{content: string} $validated
     *
     * @return array{
     *     user_message: AiChatMessage,
     *     assistant_message: AiChatMessage,
     *     conversation: AiChatConversation
     * }
     */
    public function __invoke(
        AiChatConversation $conversation,
        User $user,
        array $validated,
    ): array {
        $dailyLimit = config('ai-chat.daily_limit');

        $todayAnswerCount = AiChatMessage::query()
            ->whereHas('conversation', function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            })
            ->where('role', AiChatMessageRole::Assistant)
            ->where('status', AiChatMessageStatus::Completed)
            ->whereDate('created_at', today())
            ->count();

        if ($todayAnswerCount >= $dailyLimit) {
            throw new TooManyRequestsHttpException(
                message: '本日のAIチャット利用上限に達しました。',
            );
        }

        $conversation->loadMissing([
            'enrollment.certification',
            'section',
        ]);

        $history = $conversation->messages()
            ->orderBy('created_at')
            ->get();

        $userMessage = AiChatMessage::create([
            'ai_chat_conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::User,
            'status' => AiChatMessageStatus::Completed,
            'content' => $validated['content'],
        ]);

        $assistantMessage = AiChatMessage::create([
            'ai_chat_conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Pending,
            'content' => '',
        ]);

        $prompt = $this->buildPrompt(
            $conversation,
            $history,
            $validated['content'],
        );

        $startedAt = microtime(true);

        try {
            $result = $this->geminiService->generate($prompt);

            $responseTimeMs = (int) round(
                (microtime(true) - $startedAt) * 1000,
            );

            $assistantMessage->update([
                'status' => AiChatMessageStatus::Completed,
                'content' => $result['text'],
                'model' => $result['model'],
                'input_tokens' => $result['input_tokens'],
                'output_tokens' => $result['output_tokens'],
                'response_time_ms' => $responseTimeMs,
            ]);

            if (
                config('ai-chat.auto_title.enabled')
                && $conversation->auto_title_enabled
            ) {
                $conversation->update([
                    'title' => Str::limit(
                        $result['title'],
                        100,
                        '',
                    ),
                ]);
            }

            $conversation->update([
                'last_message_at' => now(),
            ]);

            return [
                'user_message' => $userMessage->fresh(),
                'assistant_message' => $assistantMessage->fresh(),
                'conversation' => $conversation->fresh(),
            ];
        } catch (Throwable $e) {
            $responseTimeMs = (int) round(
                (microtime(true) - $startedAt) * 1000,
            );

            $assistantMessage->update([
                'status' => AiChatMessageStatus::Error,
                'error_detail' => $e->getMessage(),
                'response_time_ms' => $responseTimeMs,
            ]);

            throw $e;
        }
    }

    /**
     * @param Collection<int, AiChatMessage> $history
     */
    private function buildPrompt(
        AiChatConversation $conversation,
        Collection $history,
        string $currentContent,
    ): string {
        $prompt = <<<'PROMPT'
あなたはCertify LMSの学習支援AIです。
受講生の学習を支援することを目的として、質問に分かりやすく正確に回答してください。

以下の学習コンテキストと会話履歴を参考にして、今回の質問に回答してください。

【学習コンテキスト】
PROMPT;

        if ($conversation->section !== null) {
            $prompt .= "\n現在のSection: {$conversation->section->title}\n";
        }

        if ($conversation->enrollment?->certification !== null) {
            $prompt .= sprintf(
                "現在のCertification: %s\n",
                $conversation->enrollment->certification->name,
            );
        }

        $prompt .= "\n【現在のタイトル】\n";
        $prompt .= $conversation->title !== null
            ? "{$conversation->title}\n"
            : 'まだタイトルはありません。今回の質問から適切なタイトルを付けてください。';

        $prompt .= <<<'PROMPT'

【会話履歴】
PROMPT;

        foreach ($history as $message) {
            $role = $message->role === AiChatMessageRole::User
                ? '受講生'
                : 'AI';

            $prompt .= "\n{$role}: {$message->content}\n";
        }

        $prompt .= <<<PROMPT

【今回の質問】
受講生: {$currentContent}

【タイトルについて】
現在のタイトルが会話全体の内容を適切に表している場合は、
現在のタイトルをそのまま使用してください。

今回の質問によって会話の主題が大きく変化し、
現在のタイトルでは会話内容を適切に表せなくなった場合のみ、
新しいタイトルを付けてください。

タイトルは学習ノートとして後から見返しやすい、
簡潔で具体的なものにしてください。
タイトルは100文字以内にしてください。

【出力形式】
必ず以下の形式だけで出力してください。

TITLE:
タイトル

ANSWER:
受講生への回答
PROMPT;

        return $prompt;
    }
}
