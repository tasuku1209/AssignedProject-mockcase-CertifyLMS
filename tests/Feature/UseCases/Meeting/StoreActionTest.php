<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Exceptions\GoogleCalendar\GoogleOAuthTokenException;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Exceptions\Mentoring\MeetingNoAvailableCoachException;
use App\Exceptions\Mentoring\MeetingOutOfAvailabilityException;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\GoogleCredential;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReservedNotification;
use App\Services\GoogleCalendarService;
use App\Services\MeetingAvailabilityService;
use App\UseCases\Meeting\StoreAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    private function attachCoach(
        Certification $certification,
        User $coach,
        User $admin,
    ): void {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    public function test_creates_reserved_meeting_with_selected_coach(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 3,
            ]);

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create([
                'meeting_url' => 'https://meet.example.com/coach-room',
            ]);

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->attachCoach(
            $certification,
            $coach,
            User::factory()->admin()->create(),
        );

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(Carbon::MONDAY)
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
        $meeting = app(StoreAction::class)(
            $enrollment,
            $student,
            $scheduledAt,
            '相談したい',
        );

        // Assert
        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'student_id' => $student->id,
            'coach_id' => $coach->id,
            'enrollment_id' => $enrollment->id,
            'status' => MeetingStatus::Reserved->value,
            'topic' => '相談したい',
            'meeting_url_snapshot' => 'https://meet.example.com/coach-room',
        ]);
    }

    public function test_throws_when_meeting_quota_is_insufficient(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 0,
            ]);

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->attachCoach(
            $certification,
            $coach,
            User::factory()->admin()->create(),
        );

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $scheduledAt = now()
            ->startOfDay()
            ->next(Carbon::MONDAY)
            ->setTime(10, 0);

        // Act / Assert
        $this->expectException(InsufficientMeetingQuotaException::class);

        app(StoreAction::class)(
            $enrollment,
            $student,
            $scheduledAt,
            '相談したい',
        );

        $this->assertDatabaseCount('meetings', 0);
    }

    public function test_throws_when_scheduled_time_is_outside_availability(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 3,
            ]);

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->attachCoach(
            $certification,
            $coach,
            User::factory()->admin()->create(),
        );

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(Carbon::MONDAY)
            ->timeRange('09:00:00', '10:00:00')
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

        // Act / Assert
        $this->expectException(
            MeetingOutOfAvailabilityException::class
        );

        app(StoreAction::class)(
            $enrollment,
            $student,
            $scheduledAt,
            '相談したい',
        );

        $this->assertDatabaseCount('meetings', 0);
    }

    public function test_throws_when_no_coach_is_available(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 3,
            ]);

        $certification = Certification::factory()
            ->published()
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

        $this->mock(MeetingAvailabilityService::class, function ($mock): void {
            $mock->shouldReceive('validateSlot')
                ->once()
                ->andReturnNull();
        });

        // Act / Assert
        $this->expectException(MeetingNoAvailableCoachException::class);

        app(StoreAction::class)(
            $enrollment,
            $student,
            $scheduledAt,
            '相談したい',
        );
    }

    public function test_selects_least_loaded_coach(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 3,
            ]);

        $admin = User::factory()->admin()->create();

        $coachWithMoreLoad = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $coachWithLessLoad = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->attachCoach(
            $certification,
            $coachWithMoreLoad,
            $admin,
        );

        $this->attachCoach(
            $certification,
            $coachWithLessLoad,
            $admin,
        );

        CoachAvailability::factory()
            ->forCoach($coachWithMoreLoad)
            ->onDay(Carbon::MONDAY)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        CoachAvailability::factory()
            ->forCoach($coachWithLessLoad)
            ->onDay(Carbon::MONDAY)
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

        Meeting::factory()
            ->for($coachWithMoreLoad, 'coach')
            ->for(
                Enrollment::factory()
                    ->for(User::factory()->student()->create(), 'user')
                    ->for($certification)
                    ->learning()
                    ->create()
            )
            ->create([
                'status' => MeetingStatus::Completed->value,
                'scheduled_at' => now()->subDays(5),
            ]);

        Meeting::factory()
            ->for($coachWithMoreLoad, 'coach')
            ->for(
                Enrollment::factory()
                    ->for(User::factory()->student()->create(), 'user')
                    ->for($certification)
                    ->learning()
                    ->create()
            )
            ->create([
                'status' => MeetingStatus::Completed->value,
                'scheduled_at' => now()->subDays(4),
            ]);

        // Act
        $meeting = app(StoreAction::class)(
            $enrollment,
            $student,
            $scheduledAt,
            '相談したい',
        );

        // Assert
        $this->assertSame(
            $coachWithLessLoad->id,
            $meeting->coach_id,
        );
    }

    public function test_consumes_meeting_quota_and_links_transaction(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 3,
            ]);

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->attachCoach(
            $certification,
            $coach,
            User::factory()->admin()->create(),
        );

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(Carbon::MONDAY)
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
        $meeting = app(StoreAction::class)(
            $enrollment,
            $student,
            $scheduledAt,
            '相談したい',
        );

        // Assert
        $this->assertNotNull(
            $meeting->meeting_quota_transaction_id
        );

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'id' => $meeting->meeting_quota_transaction_id,
            'user_id' => $student->id,
            'type' => MeetingQuotaTransactionType::Consumed->value,
            'amount' => -1,
            'related_meeting_id' => $meeting->id,
        ]);
    }

    public function test_creates_google_calendar_event_for_connected_coach(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 3,
            ]);

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create([
                'meeting_url' => 'https://meet.example.com/coach-room',
            ]);

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->attachCoach(
            $certification,
            $coach,
            User::factory()->admin()->create(),
        );

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(Carbon::MONDAY)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $credential = GoogleCredential::factory()
            ->forUser($coach)
            ->create();

        $scheduledAt = now()
            ->startOfDay()
            ->next(Carbon::MONDAY)
            ->setTime(10, 0);

        $googleEventId = 'google-event-123';

        $mock = Mockery::mock(GoogleCalendarService::class);

        $mock->shouldReceive('createEvent')
            ->once()
            ->with(
                Mockery::on(
                    fn (GoogleCredential $actual) => $actual->is($credential)
                ),
                '面談：'.$student->name,
                Mockery::on(
                    fn (Carbon $actual) => $actual->equalTo($scheduledAt)
                ),
                Mockery::on(
                    fn (Carbon $actual) => $actual->equalTo(
                        $scheduledAt->copy()->addHour()
                    )
                ),
                'https://meet.example.com/coach-room',
            )
            ->andReturn($googleEventId);

        $this->app->instance(
            GoogleCalendarService::class,
            $mock
        );

        // Act
        $meeting = app(StoreAction::class)(
            $enrollment,
            $student,
            $scheduledAt,
            '相談したい',
        );

        // Assert
        $this->assertSame(
            $googleEventId,
            $meeting->fresh()->google_event_id,
        );

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'google_event_id' => $googleEventId,
        ]);
    }

    public function test_does_not_create_google_calendar_event_for_unconnected_coach(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 3,
            ]);

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->attachCoach(
            $certification,
            $coach,
            User::factory()->admin()->create(),
        );

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(Carbon::MONDAY)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $mock = Mockery::mock(GoogleCalendarService::class);

        $mock->shouldNotReceive('createEvent');

        $this->app->instance(
            GoogleCalendarService::class,
            $mock
        );

        // Act
        $meeting = app(StoreAction::class)(
            $enrollment,
            $student,
            $scheduledAt = now()
                ->startOfDay()
                ->next(Carbon::MONDAY)
                ->setTime(10, 0),
            '相談したい',
        );

        // Assert
        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'google_event_id' => null,
        ]);
    }

    public function test_reservation_succeeds_when_google_calendar_event_creation_fails(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 3,
            ]);

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->attachCoach(
            $certification,
            $coach,
            User::factory()->admin()->create(),
        );

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(Carbon::MONDAY)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        GoogleCredential::factory()
            ->forUser($coach)
            ->create();

        $mock = Mockery::mock(GoogleCalendarService::class);

        $mock->shouldReceive('createEvent')
            ->once()
            ->andThrow(new GoogleOAuthTokenException);

        $this->app->instance(
            GoogleCalendarService::class,
            $mock
        );

        $scheduledAt = now()
            ->startOfDay()
            ->next(Carbon::MONDAY)
            ->setTime(10, 0);

        // Act
        $meeting = app(StoreAction::class)(
            $enrollment,
            $student,
            $scheduledAt,
            '相談したい',
        );

        // Assert
        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'student_id' => $student->id,
            'coach_id' => $coach->id,
            'status' => MeetingStatus::Reserved->value,
            'google_event_id' => null,
        ]);
    }

    public function test_excludes_coach_with_existing_reserved_meeting_at_same_time(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 3,
            ]);

        $admin = User::factory()->admin()->create();

        $busyCoach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $availableCoach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->attachCoach($certification, $busyCoach, $admin);
        $this->attachCoach($certification, $availableCoach, $admin);

        $scheduledAt = now()
            ->startOfDay()
            ->next(Carbon::MONDAY)
            ->setTime(10, 0);

        CoachAvailability::factory()
            ->forCoach($busyCoach)
            ->onDay(Carbon::MONDAY)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        CoachAvailability::factory()
            ->forCoach($availableCoach)
            ->onDay(Carbon::MONDAY)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherEnrollment = Enrollment::factory()
            ->for($otherStudent, 'user')
            ->for($certification)
            ->learning()
            ->create();

        Meeting::factory()
            ->for($busyCoach, 'coach')
            ->for($otherStudent, 'student')
            ->for($otherEnrollment, 'enrollment')
            ->create([
                'scheduled_at' => $scheduledAt,
                'status' => MeetingStatus::Reserved->value,
            ]);

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        // Act
        $meeting = app(StoreAction::class)(
            $enrollment,
            $student,
            $scheduledAt,
            '相談したい',
        );

        // Assert
        $this->assertSame(
            $availableCoach->id,
            $meeting->coach_id,
        );
    }

    public function test_excludes_coach_with_existing_completed_meeting_at_same_time(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 3,
            ]);

        $admin = User::factory()->admin()->create();

        $busyCoach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $availableCoach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->attachCoach($certification, $busyCoach, $admin);
        $this->attachCoach($certification, $availableCoach, $admin);

        $scheduledAt = now()
            ->startOfDay()
            ->next(Carbon::MONDAY)
            ->setTime(10, 0);

        CoachAvailability::factory()
            ->forCoach($busyCoach)
            ->onDay(Carbon::MONDAY)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        CoachAvailability::factory()
            ->forCoach($availableCoach)
            ->onDay(Carbon::MONDAY)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherEnrollment = Enrollment::factory()
            ->for($otherStudent, 'user')
            ->for($certification)
            ->learning()
            ->create();

        Meeting::factory()
            ->for($busyCoach, 'coach')
            ->for($otherStudent, 'student')
            ->for($otherEnrollment, 'enrollment')
            ->create([
                'scheduled_at' => $scheduledAt,
                'status' => MeetingStatus::Completed->value,
            ]);

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        // Act
        $meeting = app(StoreAction::class)(
            $enrollment,
            $student,
            $scheduledAt,
            '相談したい',
        );

        // Assert
        $this->assertSame(
            $availableCoach->id,
            $meeting->coach_id,
        );
    }

    public function test_notifies_in_progress_coach_after_commit(): void
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
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $this->attachCoach(
            $certification,
            $coach,
            User::factory()->admin()->create(),
        );

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(Carbon::MONDAY)
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
        $meeting = app(StoreAction::class)(
            $enrollment,
            $student,
            $scheduledAt,
            '相談したい',
        );

        // Assert
        Notification::assertSentTo(
            $coach,
            MeetingReservedNotification::class,
        );
    }
}
