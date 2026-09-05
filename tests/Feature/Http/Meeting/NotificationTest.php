<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Meeting;

use App\Enums\MeetingStatus;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingCanceledNotification;
use App\Notifications\MeetingReservedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_sends_reserved_notification_to_assigned_coach(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create(['max_meetings' => 3]);

        $admin = User::factory()->admin()->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(1)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $scheduledAt = now()
            ->startOfDay()
            ->next(Carbon::MONDAY)
            ->setTime(10, 0);

        // Act
        $response = $this->actingAs($student)
            ->post(route('meetings.store', $enrollment), [
                'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
                'topic' => '面談について相談したい',
            ]);

        // Assert
        $response->assertRedirect();

        $meeting = Meeting::query()
            ->where('enrollment_id', $enrollment->id)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame(
            MeetingStatus::Reserved,
            $meeting->status,
        );

        $this->assertSame($coach->id, $meeting->coach_id);

        Notification::assertSentTo(
            $coach,
            MeetingReservedNotification::class,
        );

        Notification::assertNotSentTo(
            $student,
            MeetingReservedNotification::class,
        );
    }

    public function test_store_does_not_notify_withdrawn_coach(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 3,
            ]);

        $admin = User::factory()
            ->admin()
            ->inProgress()
            ->create();

        $coach = User::factory()
            ->coach()
            ->withdrawn()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(1)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $scheduledAt = now()
            ->startOfDay()
            ->next(Carbon::MONDAY)
            ->setTime(10, 0);

        // Act
        $response = $this->actingAs($student)
            ->post(route('meetings.store', $enrollment), [
                'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
                'topic' => '退会済みコーチへの通知確認',
            ]);

        // Assert
        $response->assertRedirect();

        Notification::assertNotSentTo(
            $coach,
            MeetingReservedNotification::class,
        );
    }

    public function test_cancel_sends_notification_to_coach_when_student_cancels(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create(['max_meetings' => 3]);

        $admin = User::factory()->admin()->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create([
                'meeting_url' => 'https://meet.example.com/coach-room',
            ]);

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->attachCoach($certification, $coach, $admin);

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forEnrollment($enrollment)
            ->forStudent($student)
            ->forCoach($coach)
            ->create([
                'scheduled_at' => now()->addDay(),
            ]);

        // Act
        $response = $this->actingAs($student)
            ->post(route('meetings.cancel', $meeting));

        // Assert
        $response->assertRedirect();

        $meeting->refresh();

        $this->assertSame(
            MeetingStatus::Canceled,
            $meeting->status,
        );

        $this->assertSame(
            $student->id,
            $meeting->canceled_by_user_id,
        );

        Notification::assertSentTo(
            $coach,
            MeetingCanceledNotification::class,
        );

        Notification::assertNotSentTo(
            $student,
            MeetingCanceledNotification::class,
        );
    }

    public function test_cancel_sends_notification_to_student_when_coach_cancels(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create(['max_meetings' => 3]);

        $admin = User::factory()->admin()->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->attachCoach($certification, $coach, $admin);

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forEnrollment($enrollment)
            ->forStudent($student)
            ->forCoach($coach)
            ->create([
                'scheduled_at' => now()->addDay(),
            ]);

        // Act
        $response = $this->actingAs($coach)
            ->post(route('meetings.cancel', $meeting));

        // Assert
        $response->assertRedirect();

        $meeting->refresh();

        $this->assertSame(
            MeetingStatus::Canceled,
            $meeting->status,
        );

        $this->assertSame(
            $coach->id,
            $meeting->canceled_by_user_id,
        );

        Notification::assertSentTo(
            $student,
            MeetingCanceledNotification::class,
        );

        Notification::assertNotSentTo(
            $coach,
            MeetingCanceledNotification::class,
        );
    }

    private function attachCoach(
        Certification $certification,
        User $coach,
        User $admin,
    ): void {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cancel_does_not_send_notification_to_graduated_student(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()
            ->student()
            ->graduated()
            ->create();

        $admin = User::factory()->admin()->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->attachCoach($certification, $coach, $admin);

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forEnrollment($enrollment)
            ->forStudent($student)
            ->forCoach($coach)
            ->create([
                'scheduled_at' => now()->addDay(),
            ]);

        // Act
        $response = $this->actingAs($coach)
            ->post(route('meetings.cancel', $meeting));

        // Assert
        $response->assertRedirect();

        $meeting->refresh();

        $this->assertSame(
            MeetingStatus::Canceled,
            $meeting->status,
        );

        $this->assertSame(
            $coach->id,
            $meeting->canceled_by_user_id,
        );

        Notification::assertNotSentTo(
            $student,
            MeetingCanceledNotification::class,
        );
    }

    public function test_cancel_does_not_notify_withdrawn_coach(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 3,
            ]);

        $coach = User::factory()
            ->coach()
            ->withdrawn()
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->learning()
            ->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forEnrollment($enrollment)
            ->forStudent($student)
            ->forCoach($coach)
            ->create([
                'scheduled_at' => now()->addDay(),
            ]);

        // Act
        $response = $this->actingAs($student)
            ->post(route('meetings.cancel', $meeting));

        // Assert
        $response->assertRedirect(
            route('meetings.show', $meeting),
        );

        $meeting->refresh();

        $this->assertSame(
            MeetingStatus::Canceled,
            $meeting->status,
        );

        Notification::assertNotSentTo(
            $coach,
            MeetingCanceledNotification::class,
        );
    }
}
