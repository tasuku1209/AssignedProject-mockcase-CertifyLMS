<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EnrollmentStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
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
 * - 当日の成功回答数を18件生成
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

        $enrollment = $student->enrollments()
            ->where('status', EnrollmentStatus::Learning->value)
            ->get()
            ->first(function (Enrollment $enrollment): bool {
                return Section::query()
                    ->whereHas(
                        'chapter.part',
                        fn ($query) => $query->where(
                            'certification_id',
                            $enrollment->certification_id,
                        ),
                    )
                    ->exists();
            });

        if ($enrollment === null) {
            $this->command?->warn(
                'AiChatSeeder: Sectionが存在する受講中資格が見つかりません。先に EnrollmentSeeder と ContentSeeder を実行してください。',
            );

            return;
        }

        $section = Section::query()
            ->whereHas(
                'chapter.part',
                fn ($query) => $query->where(
                    'certification_id',
                    $enrollment->certification_id,
                ),
            )
            ->first();

        if ($section === null) {
            $this->command?->warn(
                'AiChatSeeder: 対象資格のSectionが存在しません。先に ContentSeeder を実行してください。',
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
     * 当日の成功回答18件を含む。
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

        for ($i = 0; $i < 13; $i++) {
            $createdAt = now()->subMinutes((13 - $i) * 5);

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
