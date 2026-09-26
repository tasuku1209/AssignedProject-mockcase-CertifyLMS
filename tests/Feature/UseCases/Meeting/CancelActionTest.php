<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\GoogleCredential;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingCanceledNotification;
use App\Services\GoogleCalendarService;
use App\UseCases\Meeting\CancelAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class CancelActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_throws_when_meeting_is_not_reserved(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $meeting = Meeting::factory()
            ->canceled()
            ->forCoach($coach)
            ->forStudent($student)
            ->create();

        // Act / Assert
        $this->expectException(MeetingStatusTransitionException::class);

        app(CancelAction::class)($meeting, $student);
    }

    public function test_throws_when_meeting_has_already_started(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->subMinute(),
            ]);

        // Act / Assert
        $this->expectException(MeetingAlreadyStartedException::class);

        app(CancelAction::class)($meeting, $student);
    }

    public function test_cancels_meeting_and_refunds_meeting_quota(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 5,
            ]);

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
            ]);

        // Act
        $result = app(CancelAction::class)($meeting, $student);

        // Assert
        $canceledMeeting = $result->fresh();

        $this->assertSame(
            MeetingStatus::Canceled,
            $canceledMeeting->status,
        );

        $this->assertSame(
            $student->id,
            $canceledMeeting->canceled_by_user_id,
        );

        $this->assertNotNull($canceledMeeting->canceled_at);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'related_meeting_id' => $meeting->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
            'amount' => 1,
        ]);
    }

    public function test_notifies_coach_when_student_cancels(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
            ]);

        // Act
        app(CancelAction::class)($meeting, $student);

        // Assert
        Notification::assertSentTo(
            $coach,
            MeetingCanceledNotification::class,
        );
    }

    public function test_notifies_student_when_coach_cancels(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
            ]);

        // Act
        app(CancelAction::class)($meeting, $coach);

        // Assert
        Notification::assertSentTo(
            $student,
            MeetingCanceledNotification::class,
        );
    }

    public function test_deletes_google_calendar_event_for_connected_coach(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
                'google_event_id' => 'google-event-123',
            ]);

        $credential = GoogleCredential::factory()
            ->forUser($coach)
            ->create();

        $mock = Mockery::mock(GoogleCalendarService::class);

        $mock->shouldReceive('deleteEvent')
            ->once()
            ->with(
                Mockery::on(
                    fn (GoogleCredential $actual): bool => $actual->is($credential)
                ),
                'google-event-123',
            );

        $this->app->instance(GoogleCalendarService::class, $mock);

        // Act
        app(CancelAction::class)($meeting, $student);

        // Assert
        $this->assertSame(
            MeetingStatus::Canceled,
            $meeting->fresh()->status,
        );
    }
}
