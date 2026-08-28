<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 開発用 受講登録メモシーダー。
 *
 * 各コーチが担当する資格の全 Enrollment に対して、
 * コーチ本人が作成したメモを2～3件ずつ投入する。
 *
 * - coach1 / coach2 の担当資格に紐づく全 Enrollment が対象
 * - 同一資格を複数コーチが担当している場合は、それぞれのコーチがメモを作成
 * - 担当外資格の Enrollment には対象コーチのメモを投入しない
 *
 * 依存順序:
 * UserSeeder → CertificationSeeder → EnrollmentSeeder → EnrollmentNoteSeeder
 */
final class EnrollmentNoteSeeder extends Seeder
{
    public function run(): void
    {
        $coaches = User::query()
            ->where('role', UserRole::Coach->value)
            ->whereIn('email', [
                'coach@certify-lms.test',
                'coach2@certify-lms.test',
            ])
            ->get();

        if ($coaches->count() < 2) {
            $this->command?->warn(
                'EnrollmentNoteSeeder: 固定コーチが存在しません。先に UserSeeder を実行してください。'
            );

            return;
        }

        foreach ($coaches as $coach) {
            $this->seedCoachNotes($coach);
        }
    }

    /**
     * 指定したコーチの担当資格に紐づく Enrollment へメモを投入する。
     */
    private function seedCoachNotes(User $coach): void
    {
        $enrollments = Enrollment::query()
            ->whereHas('certification.coaches', function ($query) use ($coach) {
                $query->where('users.id', $coach->id);
            })
            ->get();

        if ($enrollments->isEmpty()) {
            $this->command?->warn(
                "EnrollmentNoteSeeder: {$coach->email} の担当資格に紐づく受講登録がありません。先に EnrollmentSeeder を実行してください。"
            );

            return;
        }

        foreach ($enrollments as $enrollment) {
            $this->createNotes($enrollment, $coach);
        }
    }

    /**
     * 1つの Enrollment に2～3件のメモを投入する。
     */
    private function createNotes(Enrollment $enrollment, User $coach): void
    {
        $noteCount = fake()->numberBetween(2, 3);

        for ($i = 0; $i < $noteCount; $i++) {
            EnrollmentNote::factory()
                ->forEnrollment($enrollment)
                ->forCoach($coach)
                ->create([
                    'created_at' => now()->subDays(
                        ($noteCount - $i) * 3
                    ),
                ]);
        }
    }
}
