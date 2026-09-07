<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

final class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->where('email', 'admin@certify-lms.test')
            ->first();

        $certification = Certification::query()
            ->where('name', '基本情報技術者試験')
            ->where('status', 'published')
            ->first();

        $student = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        if (
            $admin === null
            || $certification === null
            || $student === null
        ) {
            $this->command?->warn(
                'AnnouncementSeeder: 必要な固定データが存在しません。先に UserSeeder / CertificationSeeder を実行してください。'
            );

            return;
        }

        $this->seedAllStudentsAnnouncement($admin);

        $this->seedCertificationAnnouncement(
            $admin,
            $certification,
        );

        $this->seedUserAnnouncement(
            $admin,
            $student,
        );
    }

    /**
     * 全受講生向けのお知らせを投入する。
     */
    private function seedAllStudentsAnnouncement(User $admin): void
    {
        $announcement = Announcement::factory()
            ->allStudents()
            ->create([
                'title' => '全受講生へのお知らせ',
                'body' => 'Certify LMSをご利用の受講生の皆様へ。サービスに関するお知らせです。',
                'created_by_user_id' => $admin->id,
            ]);

        $students = User::query()
            ->where('role', UserRole::Student->value)
            ->whereIn('status', [
                UserStatus::InProgress->value,
                UserStatus::Graduated->value,
            ])
            ->get();

        $this->dispatchNotifications($announcement, $students);
    }

    /**
     * 資格指定のお知らせを投入する。
     */
    private function seedCertificationAnnouncement(
        User $admin,
        Certification $certification,
    ): void {
        $announcement = Announcement::factory()
            ->forCertification($certification)
            ->create([
                'title' => '基本情報技術者試験コースのお知らせ',
                'body' => '基本情報技術者試験コースを受講中の皆様へ。コースに関するお知らせです。',
                'created_by_user_id' => $admin->id,
            ]);

        $students = User::query()
            ->where('role', UserRole::Student->value)
            ->whereIn('status', [
                UserStatus::InProgress->value,
                UserStatus::Graduated->value,
            ])
            ->whereHas('enrollments', function ($query) use ($certification) {
                $query
                    ->where('certification_id', $certification->id)
                    ->whereIn('status', [
                        EnrollmentStatus::Learning->value,
                        EnrollmentStatus::Passed->value,
                    ]);
            })
            ->get();

        $this->dispatchNotifications($announcement, $students);
    }

    /**
     * ユーザー指定のお知らせを投入する。
     */
    private function seedUserAnnouncement(
        User $admin,
        User $student,
    ): void {
        $announcement = Announcement::factory()
            ->forUser($student)
            ->create([
                'title' => '受講生花子さんへのお知らせ',
                'body' => '受講生花子さんへ。個別のお知らせです。',
                'created_by_user_id' => $admin->id,
            ]);

        $students = User::query()
            ->where('id', $student->id)
            ->where('role', UserRole::Student->value)
            ->whereIn('status', [
                UserStatus::InProgress->value,
                UserStatus::Graduated->value,
            ])
            ->get();

        $this->dispatchNotifications($announcement, $students);
    }

    /**
     * お知らせの対象者へDatabase Notificationを投入する。
     *
     * @param Collection<int, User> $students
     */
    private function dispatchNotifications(
        Announcement $announcement,
        Collection $students,
    ): void {
        foreach ($students as $student) {
            $notification = new AdminAnnouncementNotification(
                $announcement,
            );

            $createdAt = now();

            DatabaseNotification::create([
                'id' => (string) Str::uuid(),
                'type' => $notification::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $student->id,
                'data' => $notification->toArray($student),
                'read_at' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $announcement->update([
            'dispatched_count' => $students->count(),
            'dispatched_at' => now(),
        ]);
    }
}
