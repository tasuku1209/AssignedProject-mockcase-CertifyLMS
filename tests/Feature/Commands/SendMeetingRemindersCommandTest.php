<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\MeetingStatus;
use App\Enums\UserStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendMeetingRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_eve_window_sends_reminders_to_student_and_coach_for_tomorrow_reserved_meeting(): void
    {
        // Arrange
        Notification::fake();

        $meeting = Meeting::factory()->create([
            'scheduled_at' => now()->addDay()->setTime(15, 0),
            'status' => MeetingStatus::Reserved,
        ]);

        // Act
        $this->artisan('notifications:send-meeting-reminders --window=eve')
            ->assertExitCode(0)
            ->expectsOutputToContain('1件の対象面談を確認しました。');

        // Assert
        Notification::assertSentTo(
            $meeting->student,
            MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification) use ($meeting): bool {
                return $notification
                    ->toArray($meeting->student)['reminder_window'] === 'eve';
            },
        );

        Notification::assertSentTo(
            $meeting->coach,
            MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification) use ($meeting): bool {
                return $notification
                    ->toArray($meeting->coach)['reminder_window'] === 'eve';
            },
        );
    }

    public function test_one_hour_before_window_sends_reminders_to_student_and_coach_for_meeting_within_one_hour(): void
    {
        // Arrange
        Notification::fake();

        $meeting = Meeting::factory()->create([
            'scheduled_at' => now()->addMinutes(30),
            'status' => MeetingStatus::Reserved,
        ]);

        // Act
        $this->artisan('notifications:send-meeting-reminders --window=one_hour_before')
            ->assertExitCode(0)
            ->expectsOutputToContain('1件の対象面談を確認しました。');

        // Assert
        Notification::assertSentTo(
            $meeting->student,
            MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification) use ($meeting): bool {
                return $notification
                    ->toArray($meeting->student)['reminder_window'] === 'one_hour_before';
            },
        );

        Notification::assertSentTo(
            $meeting->coach,
            MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification) use ($meeting): bool {
                return $notification
                    ->toArray($meeting->coach)['reminder_window'] === 'one_hour_before';
            },
        );
    }

    public function test_canceled_and_completed_meetings_are_not_reminded(): void
    {
        // Arrange
        Notification::fake();

        $canceledMeeting = Meeting::factory()->create([
            'scheduled_at' => now()->addDay()->setTime(15, 0),
            'status' => MeetingStatus::Canceled,
        ]);

        $completedMeeting = Meeting::factory()->create([
            'scheduled_at' => now()->addDay()->setTime(16, 0),
            'status' => MeetingStatus::Completed,
        ]);

        // Act
        $this->artisan('notifications:send-meeting-reminders --window=eve')
            ->assertExitCode(0)
            ->expectsOutputToContain('0件の対象面談を確認しました。');

        // Assert
        Notification::assertNotSentTo(
            $canceledMeeting->student,
            MeetingReminderNotification::class,
        );

        Notification::assertNotSentTo(
            $canceledMeeting->coach,
            MeetingReminderNotification::class,
        );

        Notification::assertNotSentTo(
            $completedMeeting->student,
            MeetingReminderNotification::class,
        );

        Notification::assertNotSentTo(
            $completedMeeting->coach,
            MeetingReminderNotification::class,
        );
    }

    public function test_graduated_student_receives_reminder(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()->student()->create([
            'status' => UserStatus::Graduated,
        ]);

        $meeting = Meeting::factory()
            ->for($student, 'student')
            ->create([
                'scheduled_at' => now()->addDay()->setTime(15, 0),
                'status' => MeetingStatus::Reserved,
            ]);

        // Act
        $this->artisan('notifications:send-meeting-reminders --window=eve')
            ->assertExitCode(0);

        // Assert
        Notification::assertSentTo(
            $student,
            MeetingReminderNotification::class,
        );

        Notification::assertSentTo(
            $meeting->coach,
            MeetingReminderNotification::class,
        );
    }

    public function test_withdrawn_student_causes_both_parties_to_not_receive_reminder(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()->student()->create([
            'status' => UserStatus::Withdrawn,
        ]);

        $meeting = Meeting::factory()
            ->for($student, 'student')
            ->create([
                'scheduled_at' => now()->addDay()->setTime(15, 0),
                'status' => MeetingStatus::Reserved,
            ]);

        // Act
        $this->artisan('notifications:send-meeting-reminders --window=eve')
            ->assertExitCode(0);

        // Assert
        Notification::assertNotSentTo(
            $student,
            MeetingReminderNotification::class,
        );

        Notification::assertNotSentTo(
            $meeting->coach,
            MeetingReminderNotification::class,
        );
    }

    public function test_withdrawn_coach_causes_both_parties_to_not_receive_reminder(): void
    {
        // Arrange
        Notification::fake();

        $coach = User::factory()->coach()->create([
            'status' => UserStatus::Withdrawn,
        ]);

        $meeting = Meeting::factory()
            ->for($coach, 'coach')
            ->create([
                'scheduled_at' => now()->addDay()->setTime(15, 0),
                'status' => MeetingStatus::Reserved,
            ]);

        // Act
        $this->artisan('notifications:send-meeting-reminders --window=eve')
            ->assertExitCode(0);

        // Assert
        Notification::assertNotSentTo(
            $coach,
            MeetingReminderNotification::class,
        );

        Notification::assertNotSentTo(
            $meeting->student,
            MeetingReminderNotification::class,
        );
    }

    public function test_already_sent_reminder_is_not_sent_again(): void
    {
        // Arrange
        $meeting = Meeting::factory()->create([
            'scheduled_at' => now()->addDay()->setTime(15, 0),
            'status' => MeetingStatus::Reserved,
        ]);

        // 実際に database notification を保存する。
        $meeting->student->notify(
            new MeetingReminderNotification($meeting, 'eve'),
        );

        $meeting->coach->notify(
            new MeetingReminderNotification($meeting, 'eve'),
        );

        // 既存通知の保存後に fake へ切り替える。
        Notification::fake();

        // Act
        $this->artisan('notifications:send-meeting-reminders --window=eve')
            ->assertExitCode(0)
            ->expectsOutputToContain('1件の対象面談を確認しました。');

        // Assert
        Notification::assertNotSentTo(
            $meeting->student,
            MeetingReminderNotification::class,
        );

        Notification::assertNotSentTo(
            $meeting->coach,
            MeetingReminderNotification::class,
        );
    }

    public function test_eve_reminder_does_not_prevent_one_hour_before_reminder(): void
    {
        // Arrange
        $meeting = Meeting::factory()->create([
            'scheduled_at' => now()->addMinutes(30),
            'status' => MeetingStatus::Reserved,
        ]);

        // 前日リマインドはすでに送信済み。
        $meeting->student->notify(
            new MeetingReminderNotification($meeting, 'eve'),
        );

        $meeting->coach->notify(
            new MeetingReminderNotification($meeting, 'eve'),
        );

        Notification::fake();

        // Act
        $this->artisan('notifications:send-meeting-reminders --window=one_hour_before')
            ->assertExitCode(0)
            ->expectsOutputToContain('1件の対象面談を確認しました。');

        // Assert
        Notification::assertSentTo(
            $meeting->student,
            MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification) use ($meeting): bool {
                return $notification
                    ->toArray($meeting->student)['reminder_window'] === 'one_hour_before';
            },
        );

        Notification::assertSentTo(
            $meeting->coach,
            MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification) use ($meeting): bool {
                return $notification
                    ->toArray($meeting->coach)['reminder_window'] === 'one_hour_before';
            },
        );
    }

    public function test_invalid_window_returns_failure(): void
    {
        // Act / Assert
        $this->artisan('notifications:send-meeting-reminders --window=invalid')
            ->expectsOutputToContain(
                '--windowには eve または one_hour_before を指定してください。',
            )
            ->assertExitCode(1);
    }
}
