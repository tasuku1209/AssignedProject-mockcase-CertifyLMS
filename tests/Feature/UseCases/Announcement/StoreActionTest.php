<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use App\UseCases\Announcement\StoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_announcement_and_dispatches_to_all_active_students(): void
    {
        // Arrange
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $inProgressStudent1 = User::factory()->student()->inProgress()->create();
        $inProgressStudent2 = User::factory()->student()->inProgress()->create();
        $graduatedStudent = User::factory()->student()->graduated()->create();

        $invitedStudent = User::factory()->student()->invited()->create();
        $withdrawnStudent = User::factory()->student()->withdrawn()->create();

        $coach = User::factory()->coach()->create();

        $validated = [
            'title' => 'システムメンテナンスのお知らせ',
            'body' => '明日午前2時からシステムメンテナンスを実施します。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ];

        // Act
        $announcement = app(StoreAction::class)(
            $admin,
            $validated,
        );

        // Assert
        $this->assertInstanceOf(Announcement::class, $announcement);

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => 'システムメンテナンスのお知らせ',
            'body' => '明日午前2時からシステムメンテナンスを実施します。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'target_certification_id' => null,
            'target_user_id' => null,
            'created_by_user_id' => $admin->id,
            'dispatched_count' => 3,
        ]);

        $this->assertNotNull(
            $announcement->fresh()->dispatched_at,
        );

        Notification::assertSentTo(
            $inProgressStudent1,
            AdminAnnouncementNotification::class,
        );

        Notification::assertSentTo(
            $inProgressStudent2,
            AdminAnnouncementNotification::class,
        );

        Notification::assertSentTo(
            $graduatedStudent,
            AdminAnnouncementNotification::class,
        );

        Notification::assertNotSentTo(
            $invitedStudent,
            AdminAnnouncementNotification::class,
        );

        Notification::assertNotSentTo(
            $withdrawnStudent,
            AdminAnnouncementNotification::class,
        );

        Notification::assertNotSentTo(
            $coach,
            AdminAnnouncementNotification::class,
        );
    }

    public function test_dispatches_only_students_with_target_certification_and_valid_enrollment_status(): void
    {
        // Arrange
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $targetCertification = Certification::factory()
            ->published()
            ->create();

        $otherCertification = Certification::factory()
            ->published()
            ->create();

        $learningStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        Enrollment::factory()
            ->for($learningStudent)
            ->for($targetCertification)
            ->learning()
            ->create();

        $passedStudent = User::factory()
            ->student()
            ->graduated()
            ->create();

        Enrollment::factory()
            ->for($passedStudent)
            ->for($targetCertification)
            ->passed()
            ->create();

        $failedStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        Enrollment::factory()
            ->for($failedStudent)
            ->for($targetCertification)
            ->failed()
            ->create();

        $otherCertificationStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        Enrollment::factory()
            ->for($otherCertificationStudent)
            ->for($otherCertification)
            ->learning()
            ->create();

        $invitedStudent = User::factory()
            ->student()
            ->invited()
            ->create();

        Enrollment::factory()
            ->for($invitedStudent)
            ->for($targetCertification)
            ->learning()
            ->create();

        $validated = [
            'title' => '基本情報技術者試験コースのお知らせ',
            'body' => '新しい教材を公開しました。',
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $targetCertification->id,
        ];

        // Act
        $announcement = app(StoreAction::class)(
            $admin,
            $validated,
        );

        // Assert
        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $targetCertification->id,
            'target_user_id' => null,
            'created_by_user_id' => $admin->id,
            'dispatched_count' => 2,
        ]);

        Notification::assertSentTo(
            $learningStudent,
            AdminAnnouncementNotification::class,
        );

        Notification::assertSentTo(
            $passedStudent,
            AdminAnnouncementNotification::class,
        );

        Notification::assertNotSentTo(
            $failedStudent,
            AdminAnnouncementNotification::class,
        );

        Notification::assertNotSentTo(
            $otherCertificationStudent,
            AdminAnnouncementNotification::class,
        );

        Notification::assertNotSentTo(
            $invitedStudent,
            AdminAnnouncementNotification::class,
        );
    }

    public function test_dispatches_only_to_specified_active_student(): void
    {
        // Arrange
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $targetStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $validated = [
            'title' => '個別のお知らせ',
            'body' => '受講状況についてご確認ください。',
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $targetStudent->id,
        ];

        // Act
        $announcement = app(StoreAction::class)(
            $admin,
            $validated,
        );

        // Assert
        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'target_type' => AnnouncementTargetType::User->value,
            'target_certification_id' => null,
            'target_user_id' => $targetStudent->id,
            'created_by_user_id' => $admin->id,
            'dispatched_count' => 1,
        ]);

        Notification::assertSentTo(
            $targetStudent,
            AdminAnnouncementNotification::class,
        );

        Notification::assertNotSentTo(
            $otherStudent,
            AdminAnnouncementNotification::class,
        );
    }

    public function test_does_not_dispatch_to_invited_specified_student(): void
    {
        // Arrange
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $invitedStudent = User::factory()
            ->student()
            ->invited()
            ->create();

        $validated = [
            'title' => '個別のお知らせ',
            'body' => 'ご案内内容をご確認ください。',
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $invitedStudent->id,
        ];

        // Act
        $announcement = app(StoreAction::class)(
            $admin,
            $validated,
        );

        // Assert
        $this->assertSame(
            0,
            $announcement->fresh()->dispatched_count,
        );

        Notification::assertNotSentTo(
            $invitedStudent,
            AdminAnnouncementNotification::class,
        );
    }

    public function test_does_not_dispatch_to_withdrawn_specified_student(): void
    {
        // Arrange
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $withdrawnStudent = User::factory()
            ->student()
            ->withdrawn()
            ->create();

        $validated = [
            'title' => '個別のお知らせ',
            'body' => 'ご案内内容をご確認ください。',
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $withdrawnStudent->id,
        ];

        // Act
        $announcement = app(StoreAction::class)(
            $admin,
            $validated,
        );

        // Assert
        $this->assertSame(
            0,
            $announcement->fresh()->dispatched_count,
        );

        Notification::assertNotSentTo(
            $withdrawnStudent,
            AdminAnnouncementNotification::class,
        );
    }

    public function test_updates_dispatched_at_when_no_students_are_targeted(): void
    {
        // Arrange
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $invitedStudent = User::factory()
            ->student()
            ->invited()
            ->create();

        $validated = [
            'title' => '個別のお知らせ',
            'body' => '対象外ユーザーへの確認用です。',
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $invitedStudent->id,
        ];

        // Act
        $announcement = app(StoreAction::class)(
            $admin,
            $validated,
        );

        // Assert
        $fresh = $announcement->fresh();

        $this->assertSame(0, $fresh->dispatched_count);
        $this->assertNotNull($fresh->dispatched_at);
    }
}
