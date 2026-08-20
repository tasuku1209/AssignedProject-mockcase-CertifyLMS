<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CertificationCoachAssignment;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

final class QaReplySeeder extends Seeder
{
    public function run(): void
    {
        $threads = QaThread::query()
            ->orderBy('created_at')
            ->get();

        if ($threads->isEmpty()) {
            $this->command?->warn(
                'QaReplySeeder: 質問が存在しません。先に QaThreadSeeder を実行してください。'
            );

            return;
        }

        $fixedStudent = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        if ($fixedStudent === null) {
            $this->command?->warn(
                'QaReplySeeder: 固定受講生が存在しません。先に UserSeeder を実行してください。'
            );

            return;
        }

        $demoStudent = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->where('email', '!=', 'student@certify-lms.test')
            ->orderBy('created_at')
            ->first();

        $coach = User::query()
            ->where('email', 'coach@certify-lms.test')
            ->first();

        $this->seedCoachReplies($coach, $threads);
        $this->seedFixedStudentReplies($fixedStudent, $threads);
        $this->seedDemoStudentReplies($demoStudent, $threads);
    }

    /**
     * 固定コーチの担当資格に属する全スレッドへ回答を投入する。
     *
     * @param Collection<int, QaThread> $threads
     */
    private function seedCoachReplies(User $coach, $threads): void
    {
        $certificationIds = CertificationCoachAssignment::query()
            ->where('user_id', $coach->id)
            ->pluck('certification_id');

        foreach ($threads as $thread) {
            if (! $certificationIds->contains($thread->certification_id)) {
                continue;
            }

            $this->createReply($thread, $coach);
        }
    }

    /**
     * 固定受講生の質問へ回答を投入する。
     *
     * 3件に1件は回答なし、それ以外は1件の回答を投入する。
     *
     * @param Collection<int, QaThread> $threads
     */
    private function seedFixedStudentReplies(User $student, $threads): void
    {
        $studentThreads = $threads
            ->where('user_id', $student->id)
            ->values();

        foreach ($studentThreads as $index => $thread) {
            if ($index % 3 === 0) {
                continue;
            }

            $this->createReply($thread, $student);
        }
    }

    /**
     * デモ受講生の質問へ回答を投入する。
     *
     * 固定受講生と同じく3件に1件をスキップする。
     * それ以外は1〜3件の回答をランダムに投入する。
     *
     * @param Collection<int, QaThread> $threads
     */
    private function seedDemoStudentReplies(User $student, $threads): void
    {
        $studentThreads = $threads
            ->where('user_id', $student->id)
            ->values();

        foreach ($studentThreads as $index => $thread) {
            if ($index % 3 === 0) {
                continue;
            }

            $replyCount = fake()->numberBetween(1, 3);

            foreach (range(1, $replyCount) as $i) {
                $this->createReply($thread, $student);
            }
        }
    }

    /**
     * 回答を1件作成する。
     *
     * 回答日時は質問日時より後、かつ1〜10日前の範囲にする。
     */
    private function createReply(QaThread $thread, User $user): void
    {
        $createdAt = fake()->dateTimeBetween(
            $thread->created_at,
            now()
        );

        QaReply::factory()
            ->forThread($thread)
            ->forUser($user)
            ->state([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])
            ->create();
    }
}
