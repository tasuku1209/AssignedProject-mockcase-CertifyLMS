<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * 開発用 AI チャットシーダー。
 *
 * 固定受講生に対して、AIチャットの履歴確認に必要な状態を生成する。
 *
 * - 教材に紐づかない会話を1件生成
 * - 教材に紐づく会話を1件生成
 * - 過去の会話履歴を生成
 * - 当日の成功回答数を40件生成
 * - AI回答がErrorとなった履歴を1件生成
 *
 * 依存順序:
 * `UserSeeder` → `CertificationSeeder` → `EnrollmentSeeder`
 * → `ContentSeeder` → 本 Seeder
 */
final class AiChatSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        if ($student === null) {
            $this->command?->warn(
                'AiChatSeeder: 固定受講生が存在しません。先に UserSeeder を実行してください。',
            );

            return;
        }

        $certification = Certification::query()
            ->where('name', '基本情報技術者試験')
            ->first();

        if ($certification === null) {
            $this->command?->warn(
                'AiChatSeeder: 基本情報技術者試験が存在しません。先に CertificationSeeder を実行してください。',
            );

            return;
        }

        $enrollment = $student->enrollments()
            ->where('certification_id', $certification->id)
            ->first();

        if ($enrollment === null) {
            $this->command?->warn(
                'AiChatSeeder: 基本情報技術者試験の受講中Enrollmentが見つかりません。先に EnrollmentSeeder を実行してください。',
            );

            return;
        }

        $section = Section::query()
            ->where('title', '1.1 2 進数の表現')
            ->first();

        if ($section === null) {
            $this->command?->warn(
                'AiChatSeeder: 基本情報技術者試験の対象Sectionが存在しません。先に ContentSeeder を実行してください。',
            );

            return;
        }

        $this->seedWithoutSectionConversation(
            $student,
            $enrollment,
        );

        $this->seedSectionConversation(
            $student,
            $enrollment,
            $section,
        );
    }

    /**
     * 教材に紐づかない会話を生成する。
     *
     * 当日の成功回答40件を含む。
     */
    private function seedWithoutSectionConversation(
        User $student,
        Enrollment $enrollment,
    ): void {
        $conversation = AiChatConversation::factory()
            ->forUser($student)
            ->forEnrollment($enrollment)
            ->withoutSection()
            ->state([
                'title' => '学習内容についての質問',
                'last_message_at' => now(),
            ])
            ->create();

        for ($i = 0; $i < 40; $i++) {
            $createdAt = now()->subMinutes((40 - $i) * 5);

            $this->createMessagePair(
                $conversation,
                $createdAt,
            );
        }
    }

    /**
     * 教材に紐づく会話を生成する。
     *
     * 過去の成功回答5件と、
     * 当日のError回答1件を含む。
     */
    private function seedSectionConversation(
        User $student,
        Enrollment $enrollment,
        Section $section,
    ): void {
        $conversation = AiChatConversation::factory()
            ->forUser($student)
            ->forEnrollment($enrollment)
            ->forSection($section)
            ->state([
                'title' => '教材についての質問',
                'last_message_at' => now(),
            ])
            ->create();

        for ($i = 0; $i < 5; $i++) {
            $createdAt = now()->subDays(5 - $i);

            $this->createMessagePair(
                $conversation,
                $createdAt,
            );
        }

        $this->createErrorMessagePair($conversation);
    }

    /**
     * 受講生の質問とAIの回答を1往復生成する。
     */
    private function createMessagePair(
        AiChatConversation $conversation,
        Carbon $createdAt,
    ): void {
        AiChatMessage::factory()
            ->user()
            ->forConversation($conversation)
            ->state([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])
            ->create();

        $assistantCreatedAt = $createdAt->copy()->addSeconds(5);

        AiChatMessage::factory()
            ->assistant()
            ->forConversation($conversation)
            ->state([
                'created_at' => $assistantCreatedAt,
                'updated_at' => $assistantCreatedAt,
            ])
            ->create();
    }

    /**
     * 受講生の質問とAI回答Errorを1往復生成する。
     */
    private function createErrorMessagePair(
        AiChatConversation $conversation,
    ): void {
        $userCreatedAt = now()->subMinutes(10);

        AiChatMessage::factory()
            ->user()
            ->forConversation($conversation)
            ->state([
                'created_at' => $userCreatedAt,
                'updated_at' => $userCreatedAt,
            ])
            ->create();

        $assistantCreatedAt = $userCreatedAt->copy()->addSeconds(5);

        AiChatMessage::factory()
            ->error()
            ->forConversation($conversation)
            ->state([
                'created_at' => $assistantCreatedAt,
                'updated_at' => $assistantCreatedAt,
            ])
            ->create();
    }
}
