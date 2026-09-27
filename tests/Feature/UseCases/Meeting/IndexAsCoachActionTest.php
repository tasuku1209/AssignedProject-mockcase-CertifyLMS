<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\IndexAsCoachAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexAsCoachActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_coach_meetings_in_ascending_order_for_upcoming_tab(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        $later = Meeting::factory()
            ->for($enrollment)
            ->for($coach, 'coach')
            ->create([
                'scheduled_at' => now()->addDays(2),
                'status' => MeetingStatus::Reserved->value,
            ]);

        $earlier = Meeting::factory()
            ->for($enrollment)
            ->for($coach, 'coach')
            ->create([
                'scheduled_at' => now()->addDay(),
                'status' => MeetingStatus::Reserved->value,
            ]);

        // Act
        $result = app(IndexAsCoachAction::class)(
            $coach,
            null,
            null,
        );

        // Assert
        $this->assertSame(
            [$earlier->id, $later->id],
            $result->getCollection()->pluck('id')->all(),
        );
    }

    public function test_returns_coach_meetings_in_descending_order_for_past_tab(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        $earlier = Meeting::factory()
            ->for($enrollment)
            ->for($coach, 'coach')
            ->create([
                'scheduled_at' => now()->subDays(2),
                'status' => MeetingStatus::Completed->value,
            ]);

        $later = Meeting::factory()
            ->for($enrollment)
            ->for($coach, 'coach')
            ->create([
                'scheduled_at' => now()->subDay(),
                'status' => MeetingStatus::Completed->value,
            ]);

        // Act
        $result = app(IndexAsCoachAction::class)(
            $coach,
            null,
            null,
            'past',
        );

        // Assert
        $this->assertSame(
            [$later->id, $earlier->id],
            $result->getCollection()->pluck('id')->all(),
        );
    }

    public function test_all_tab_returns_meetings_in_descending_order(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        $past = Meeting::factory()
            ->for($enrollment)
            ->for($coach, 'coach')
            ->create([
                'scheduled_at' => now()->subDay(),
                'status' => MeetingStatus::Completed->value,
            ]);

        $upcoming = Meeting::factory()
            ->for($enrollment)
            ->for($coach, 'coach')
            ->create([
                'scheduled_at' => now()->addDay(),
                'status' => MeetingStatus::Reserved->value,
            ]);

        // Act
        $result = app(IndexAsCoachAction::class)(
            $coach,
            null,
            null,
            'all',
        );

        // Assert
        $this->assertSame(
            [$upcoming->id, $past->id],
            $result->getCollection()->pluck('id')->all(),
        );
    }

    public function test_default_filter_returns_only_upcoming_meetings(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        $past = Meeting::factory()
            ->for($enrollment)
            ->for($coach, 'coach')
            ->create([
                'scheduled_at' => now()->subDay(),
                'status' => MeetingStatus::Completed->value,
            ]);

        $upcoming = Meeting::factory()
            ->for($enrollment)
            ->for($coach, 'coach')
            ->create([
                'scheduled_at' => now()->addDay(),
                'status' => MeetingStatus::Reserved->value,
            ]);

        // Act
        $result = app(IndexAsCoachAction::class)(
            $coach,
            null,
            null,
        );

        // Assert
        $this->assertSame(
            [$upcoming->id],
            $result->getCollection()->pluck('id')->all(),
        );

        $this->assertNotContains(
            $past->id,
            $result->getCollection()->pluck('id')->all(),
        );
    }

    public function test_returns_only_meetings_belonging_to_the_coach(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        $ownMeeting = Meeting::factory()
            ->for($enrollment)
            ->for($coach, 'coach')
            ->create([
                'scheduled_at' => now()->addDay(),
                'status' => MeetingStatus::Reserved->value,
            ]);

        $otherMeeting = Meeting::factory()
            ->for($enrollment)
            ->for($otherCoach, 'coach')
            ->create([
                'scheduled_at' => now()->addDays(2),
                'status' => MeetingStatus::Reserved->value,
            ]);

        // Act
        $result = app(IndexAsCoachAction::class)(
            $coach,
            null,
            null,
        );

        // Assert
        $this->assertSame(
            [$ownMeeting->id],
            $result->getCollection()->pluck('id')->all(),
        );

        $this->assertNotContains(
            $otherMeeting->id,
            $result->getCollection()->pluck('id')->all(),
        );
    }

    public function test_loads_enrollment_certification_and_student(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->create();

        $meeting = Meeting::factory()
            ->for($enrollment)
            ->for($coach, 'coach')
            ->for($student, 'student')
            ->create([
                'scheduled_at' => now()->addDay(),
                'status' => MeetingStatus::Reserved->value,
            ]);

        // Act
        $result = app(IndexAsCoachAction::class)(
            $coach,
            null,
            null,
        );

        // Assert
        $meeting = $result->getCollection()->first();

        $this->assertTrue($meeting->relationLoaded('enrollment'));
        $this->assertTrue(
            $meeting->enrollment->relationLoaded('certification'),
        );
        $this->assertTrue($meeting->relationLoaded('student'));
    }

    public function test_filters_meetings_by_student(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $enrollment = Enrollment::factory()->create();
        $otherEnrollment = Enrollment::factory()->create();

        $ownMeeting = Meeting::factory()
            ->for($enrollment)
            ->for($coach, 'coach')
            ->for($student, 'student')
            ->create([
                'scheduled_at' => now()->addDay(),
                'status' => MeetingStatus::Reserved->value,
            ]);

        $otherMeeting = Meeting::factory()
            ->for($otherEnrollment)
            ->for($coach, 'coach')
            ->for($otherStudent, 'student')
            ->create([
                'scheduled_at' => now()->addDays(2),
                'status' => MeetingStatus::Reserved->value,
            ]);

        // Act
        $result = app(IndexAsCoachAction::class)(
            $coach,
            $student->id,
            null,
        );

        // Assert
        $this->assertSame(
            [$ownMeeting->id],
            $result->getCollection()->pluck('id')->all(),
        );

        $this->assertNotContains(
            $otherMeeting->id,
            $result->getCollection()->pluck('id')->all(),
        );
    }

    public function test_filters_meetings_by_enrollment(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        $enrollment = Enrollment::factory()->create();
        $otherEnrollment = Enrollment::factory()->create();

        $ownMeeting = Meeting::factory()
            ->for($enrollment)
            ->for($coach, 'coach')
            ->create([
                'scheduled_at' => now()->addDay(),
                'status' => MeetingStatus::Reserved->value,
            ]);

        $otherMeeting = Meeting::factory()
            ->for($otherEnrollment)
            ->for($coach, 'coach')
            ->create([
                'scheduled_at' => now()->addDays(2),
                'status' => MeetingStatus::Reserved->value,
            ]);

        // Act
        $result = app(IndexAsCoachAction::class)(
            $coach,
            null,
            $enrollment->id,
        );

        // Assert
        $this->assertSame(
            [$ownMeeting->id],
            $result->getCollection()->pluck('id')->all(),
        );

        $this->assertNotContains(
            $otherMeeting->id,
            $result->getCollection()->pluck('id')->all(),
        );
    }

    public function test_paginates_meetings_by_twenty_per_page(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        Meeting::factory()
            ->count(21)
            ->for($enrollment)
            ->for($coach, 'coach')
            ->sequence(fn ($sequence) => [
                'scheduled_at' => now()->addMinutes(
                    $sequence->index + 1
                ),
                'status' => MeetingStatus::Reserved->value,
            ])
            ->create();

        // Act
        $result = app(IndexAsCoachAction::class)(
            $coach,
            null,
            null,
        );

        // Assert
        $this->assertSame(20, $result->perPage());
        $this->assertSame(21, $result->total());
        $this->assertCount(20, $result->items());
    }
}
