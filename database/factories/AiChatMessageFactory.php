<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiChatMessage>
 */
class AiChatMessageFactory extends Factory
{
    protected $model = AiChatMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_chat_conversation_id' => AiChatConversation::factory(),
            'role' => AiChatMessageRole::User,
            'status' => AiChatMessageStatus::Completed,
            'content' => fake()->randomElement([
                'この問題の考え方がよく分かりません。',
                'この部分についてもう少し詳しく教えてください。',
                '教材のこの内容を具体例で説明してください。',
                'この問題はどのように考えればよいですか？',
                '試験ではこの知識をどのように使いますか？',
                'この用語の意味をもう少し分かりやすく説明してください。',
                '似たような問題が出た場合は、どこに注意すればよいですか？',
                'この問題で間違えてしまった理由を知りたいです。',
            ]),
            'error_detail' => null,
            'model' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'response_time_ms' => null,
        ];
    }

    /**
     * 受講生の質問にする。
     */
    public function user(): static
    {
        return $this->state(fn () => [
            'role' => AiChatMessageRole::User,
            'status' => AiChatMessageStatus::Completed,
            'content' => fake()->randomElement([
                'この問題の考え方がよく分かりません。',
                'この部分についてもう少し詳しく教えてください。',
                '教材のこの内容を具体例で説明してください。',
                'この問題はどのように考えればよいですか？',
                '試験ではこの知識をどのように使いますか？',
                'この用語の意味をもう少し分かりやすく説明してください。',
                '似たような問題が出た場合は、どこに注意すればよいですか？',
                'この問題で間違えてしまった理由を知りたいです。',
            ]),
            'error_detail' => null,
            'model' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'response_time_ms' => null,
        ]);
    }

    /**
     * AIの回答にする。
     */
    public function assistant(): static
    {
        return $this->state(fn () => [
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Completed,
            'content' => fake()->randomElement([
                'まず基本的な考え方を整理すると理解しやすくなります。教材の該当部分を確認しながら、一つずつ確認してみてください。',
                'この問題では、条件を一つずつ整理して考えることがポイントです。問題文の内容を順番に確認してみましょう。',
                '最初は少し分かりにくいところですが、具体例に置き換えて考えると理解しやすくなります。',
                'この範囲は試験でも重要な内容です。今回の問題だけでなく、似た形式の問題も解いてみると理解が深まります。',
                'まず問題文に書かれている条件を整理してみましょう。そのうえで、教材で説明されている手順に沿って考えると解きやすくなります。',
                'この部分で迷うのは自然なことです。教材の基本的な説明に戻って、用語の意味から確認してみることをおすすめします。',
                '考え方としては合っています。次は、なぜその結果になるのかを順番に確認すると、より理解しやすくなります。',
                '関連する教材の章をもう一度確認するとよいでしょう。特に具体例の部分を参考にすると理解しやすくなります。',
            ]),
            'error_detail' => null,
            'model' => config('ai-chat.gemini.model'),
            'input_tokens' => fake()->numberBetween(100, 1000),
            'output_tokens' => fake()->numberBetween(50, 500),
            'response_time_ms' => fake()->numberBetween(500, 5000),
        ]);
    }

    /**
     * AIの回答取得に失敗した状態にする。
     */
    public function error(): static
    {
        return $this->state(fn () => [
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Error,
            'content' => '',
            'error_detail' => 'Geminiからの回答取得に失敗しました。',
            'model' => config('ai-chat.gemini.model'),
            'input_tokens' => null,
            'output_tokens' => null,
            'response_time_ms' => fake()->numberBetween(100, 3000),
        ]);
    }

    /**
     * AIが回答処理中の状態にする。
     */
    public function pending(): static
    {
        return $this->state(fn () => [
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Pending,
            'content' => '',
            'error_detail' => null,
            'model' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'response_time_ms' => null,
        ]);
    }

    /**
     * 指定した会話のメッセージにする。
     */
    public function forConversation(
        AiChatConversation $conversation,
    ): static {
        return $this->state(fn () => [
            'ai_chat_conversation_id' => $conversation->id,
        ]);
    }
}
