<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiChatConversation>
 */
class AiChatConversationFactory extends Factory
{
    protected $model = AiChatConversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'enrollment_id' => Enrollment::factory()->learning(),
            'section_id' => null,
            'title' => fake()->randomElement([
                '教材についての質問',
                '試験対策について相談',
                '学習内容の確認',
                '問題の解き方について',
                '資格試験の勉強について',
            ]),
            'auto_title_enabled' => true,
            'last_message_at' => now(),
        ];
    }

    /**
     * 指定したユーザーの会話にする。
     */
    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * 指定した受講情報の会話にする。
     */
    public function forEnrollment(Enrollment $enrollment): static
    {
        return $this->state(fn () => [
            'enrollment_id' => $enrollment->id,
            'user_id' => $enrollment->user_id,
        ]);
    }

    /**
     * 指定した教材セクションの会話にする。
     */
    public function forSection(Section $section): static
    {
        return $this->state(fn () => [
            'section_id' => $section->id,
        ]);
    }

    /**
     * 特定の教材に紐づかない会話にする。
     */
    public function withoutSection(): static
    {
        return $this->state(fn () => [
            'section_id' => null,
        ]);
    }

    /**
     * 会話タイトルの自動更新を無効にする。
     */
    public function autoTitleDisabled(): static
    {
        return $this->state(fn () => [
            'auto_title_enabled' => false,
        ]);
    }
}
