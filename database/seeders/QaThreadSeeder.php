<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * 質問掲示板の demo データ シーダー。
 *
 * 固定アカウント + その他の受講生の 2 軸で投入する。
 *
 * 1. 固定アカウント `student@certify-lms.test`
 *    - 公開済み資格 5 件それぞれに未解決 1 件 / 解決済み 1 件
 *    - 合計 10 件
 *    - 作成日時は 1〜10 日前でランダム
 *
 * 2. その他の受講中受講生 1 名
 *    - 公開済み資格 5 件それぞれに未解決 4 件 / 解決済み 1 件
 *    - 合計 25 件
 *    - 作成日時は 11〜30 日前でランダム
 *
 * これにより、質問掲示板の以下の実機確認に必要なデータを用意する。
 * - 未解決 / 解決済みの絞り込み
 * - 資格による絞り込み
 * - 資格 + 状態の複合絞り込み
 * - ページネーション
 * - 自分の質問の編集 / 削除 / 解決状態変更
 * - 他人の質問に対する認可
 */
final class QaThreadSeeder extends Seeder
{
    public function run(): void
    {
        $certifications = Certification::query()
            ->where('status', CertificationStatus::Published->value)
            ->orderBy('created_at')
            ->get();

        if ($certifications->isEmpty()) {
            $this->command?->warn(
                'QaThreadSeeder: 公開済み資格が存在しません。先に CertificationSeeder を実行してください。'
            );

            return;
        }

        $fixedStudent = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        if ($fixedStudent === null) {
            $this->command?->warn(
                'QaThreadSeeder: 固定受講生が存在しません。先に UserSeeder を実行してください。'
            );

            return;
        }

        $this->seedForFixedStudent($fixedStudent, $certifications);

        $demoStudent = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->where('email', '!=', 'student@certify-lms.test')
            ->orderBy('created_at')
            ->first();

        $this->seedForDemoStudent($demoStudent, $certifications);
    }

    /**
     * 固定受講生用の質問を投入する。
     *
     * 各資格につき未解決 1 件 / 解決済み 1 件。
     * 合計 10 件。
     */
    private function seedForFixedStudent(User $student, Collection $certifications): void
    {
        foreach ($certifications as $certification) {
            $this->seedUnresolvedThreads(
                student: $student,
                certification: $certification,
                count: 1,
                minDaysAgo: 1,
                maxDaysAgo: 10,
            );

            $this->seedResolvedThreads(
                student: $student,
                certification: $certification,
                count: 1,
                minDaysAgo: 1,
                maxDaysAgo: 10,
            );
        }
    }

    /**
     * 固定受講生以外の受講生用の質問を投入する。
     *
     * 各資格につき未解決 4 件 / 解決済み 1 件。
     * 合計 25 件。
     */
    private function seedForDemoStudent(User $student, Collection $certifications): void
    {
        foreach ($certifications as $certification) {
            $this->seedUnresolvedThreads(
                student: $student,
                certification: $certification,
                count: 4,
                minDaysAgo: 11,
                maxDaysAgo: 30,
            );

            $this->seedResolvedThreads(
                student: $student,
                certification: $certification,
                count: 1,
                minDaysAgo: 11,
                maxDaysAgo: 30,
            );
        }
    }

    /**
     * 未解決の質問を作成する。
     */
    private function seedUnresolvedThreads(
        User $student,
        Certification $certification,
        int $count,
        int $minDaysAgo,
        int $maxDaysAgo,
    ): void {
        QaThread::factory()
            ->unresolved()
            ->state([
                'user_id' => $student->id,
                'certification_id' => $certification->id,
                'created_at' => now()->subDays(
                    fake()->numberBetween($minDaysAgo, $maxDaysAgo)
                ),
            ])
            ->count($count)
            ->create();
    }

    /**
     * 解決済みの質問を作成する。
     */
    private function seedResolvedThreads(
        User $student,
        Certification $certification,
        int $count,
        int $minDaysAgo,
        int $maxDaysAgo,
    ): void {
        QaThread::factory()
            ->resolved()
            ->state([
                'user_id' => $student->id,
                'certification_id' => $certification->id,
                'created_at' => now()->subDays(
                    fake()->numberBetween($minDaysAgo, $maxDaysAgo)
                ),
            ])
            ->count($count)
            ->create();
    }
}
