<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class StoreAction
{
    /**
     * お知らせを作成して、対象受講生へ配信する。
     *
     * @param array<string, mixed> $validated
     */
    public function __invoke(
        User $admin,
        array $validated,
    ): Announcement {
        return DB::transaction(function () use ($admin, $validated) {
            $announcement = Announcement::create([
                'title' => $validated['title'],
                'body' => $validated['body'],
                'target_type' => $validated['target_type'],
                'target_certification_id' => $validated['target_certification_id'] ?? null,
                'target_user_id' => $validated['target_user_id'] ?? null,
                'created_by_user_id' => $admin->id,
            ]);

            $students = $this->resolveStudents($announcement);

            foreach ($students as $student) {
                $student->notify(
                    new AdminAnnouncementNotification($announcement),
                );
            }

            $announcement->update([
                'dispatched_count' => $students->count(),
                'dispatched_at' => now(),
            ]);

            return $announcement;
        });
    }

    /**
     * 配信対象となる受講生を取得する。
     *
     * @return Collection<int, User>
     */
    private function resolveStudents(Announcement $announcement): Collection
    {
        return match ($announcement->target_type) {
            AnnouncementTargetType::AllStudents => $this->allStudents(),

            AnnouncementTargetType::Certification => $this->certificationStudents(
                $announcement->target_certification_id,
            ),

            AnnouncementTargetType::User => $this->specifiedUser(
                $announcement->target_user_id,
            ),
        };
    }

    /**
     * 全受講生を取得する。
     *
     * @return Collection<int, User>
     */
    private function allStudents(): Collection
    {
        return User::query()
            ->where('role', UserRole::Student)
            ->whereIn('status', [
                UserStatus::InProgress,
                UserStatus::Graduated,
            ])
            ->get();
    }

    /**
     * 指定資格に受講登録している受講生を取得する。
     *
     * @return Collection<int, User>
     */
    private function certificationStudents(
        string $certificationId,
    ): Collection {
        return User::query()
            ->where('role', UserRole::Student)
            ->whereIn('status', [
                UserStatus::InProgress,
                UserStatus::Graduated,
            ])
            ->whereHas('enrollments', function ($query) use ($certificationId) {
                $query
                    ->where('certification_id', $certificationId)
                    ->whereIn('status', [
                        EnrollmentStatus::Learning,
                        EnrollmentStatus::Passed,
                    ]);
            })
            ->get();
    }

    /**
     * 指定受講生を取得する。
     *
     * @return Collection<int, User>
     */
    private function specifiedUser(
        string $userId,
    ): Collection {
        return User::query()
            ->where('id', $userId)
            ->where('role', UserRole::Student)
            ->whereIn('status', [
                UserStatus::InProgress,
                UserStatus::Graduated,
            ])
            ->get();
    }
}
