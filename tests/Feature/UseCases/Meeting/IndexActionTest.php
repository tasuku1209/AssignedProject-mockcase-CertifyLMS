<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Services\MeetingQuotaService;
use App\UseCases\Meeting\IndexAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_student_meetings_in_descending_scheduled_order(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->create();

        $older = Meeting::factory()
            ->for($enrollment)
            ->for($student, 'student')
            ->create([
                'scheduled_at' => now()->addDays(1),
            ]);

        $newer = Meeting::factory()
            ->for($enrollment)
            ->for($student, 'student')
            ->create([
                'scheduled_at' => now()->addDays(3),
            ]);

        $result = app(IndexAction::class)($student);

        $meetings = $result['meetings']->getCollection();

        $this->assertSame($newer->id, $meetings->first()->id);
        $this->assertSame($older->id, $meetings->last()->id);
    }

    public function test_loads_enrollment_certification_and_coach(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->create();
        $coach = User::factory()->coach()->create();

        $meeting = Meeting::factory()
            ->for($enrollment)
            ->for($student, 'student')
            ->for($coach, 'coach')
            ->create([
                'scheduled_at' => now()->addDay(),
            ]);

        $result = app(IndexAction::class)($student);

        $meeting = $result['meetings']->getCollection()->first();

        $this->assertTrue($meeting->relationLoaded('enrollment'));
        $this->assertTrue($meeting->enrollment->relationLoaded('certification'));
        $this->assertTrue($meeting->relationLoaded('coach'));
    }

    public function test_returns_only_meetings_belonging_to_student(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->create();

        $otherEnrollment = Enrollment::factory()
            ->for($otherStudent, 'user')
            ->for($certification)
            ->create();

        $meeting = Meeting::factory()
            ->for($enrollment)
            ->for($student, 'student')
            ->create([
                'scheduled_at' => now()->addDay(),
            ]);

        Meeting::factory()
            ->for($otherEnrollment)
            ->for($otherStudent, 'student')
            ->create([
                'scheduled_at' => now()->addDays(2),
            ]);

        $result = app(IndexAction::class)($student);

        $meetings = $result['meetings']->getCollection();

        $this->assertCount(1, $meetings);
        $this->assertSame($meeting->id, $meetings->first()->id);
    }

    public function test_returns_upcoming_meetings_by_default(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->create();

        $upcoming = Meeting::factory()
            ->for($enrollment)
            ->for($student, 'student')
            ->create([
                'scheduled_at' => now()->addDay(),
                'status' => MeetingStatus::Reserved->value,
            ]);

        Meeting::factory()
            ->for($enrollment)
            ->for($student, 'student')
            ->create([
                'scheduled_at' => now()->subDay(),
                'status' => MeetingStatus::Completed->value,
            ]);

        $result = app(IndexAction::class)($student);

        $meetings = $result['meetings']->getCollection();

        $this->assertCount(1, $meetings);
        $this->assertSame($upcoming->id, $meetings->first()->id);
    }

    public function test_past_tab_returns_only_past_meetings(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->create();

        $past = Meeting::factory()
            ->for($enrollment)
            ->for($student, 'student')
            ->create([
                'scheduled_at' => now()->subDay(),
                'status' => MeetingStatus::Completed->value,
            ]);

        Meeting::factory()
            ->for($enrollment)
            ->for($student, 'student')
            ->create([
                'scheduled_at' => now()->addDay(),
            ]);

        $result = app(IndexAction::class)(
            $student,
            'past',
        );

        $meetings = $result['meetings']->getCollection();

        $this->assertCount(1, $meetings);
        $this->assertSame($past->id, $meetings->first()->id);
    }

    public function test_all_tab_returns_past_and_upcoming_meetings(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->create();

        $past = Meeting::factory()
            ->for($enrollment)
            ->for($student, 'student')
            ->create([
                'scheduled_at' => now()->subDay(),
                'status' => MeetingStatus::Completed->value,
            ]);

        $upcoming = Meeting::factory()
            ->for($enrollment)
            ->for($student, 'student')
            ->create([
                'scheduled_at' => now()->addDay(),
                'status' => MeetingStatus::Reserved->value,
            ]);

        $result = app(IndexAction::class)(
            $student,
            'all',
        );

        $meetings = $result['meetings']->getCollection();

        $this->assertCount(2, $meetings);
        $this->assertSame($upcoming->id, $meetings->first()->id);
        $this->assertSame($past->id, $meetings->last()->id);
    }

    public function test_meetings_are_paginated_by_twenty_per_page(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->create();

        Meeting::factory()
            ->count(21)
            ->for($enrollment)
            ->for($student, 'student')
            ->sequence(fn ($sequence) => [
                'scheduled_at' => now()->addDays($sequence->index + 1),
            ])
            ->create();

        $result = app(IndexAction::class)($student);

        $paginator = $result['meetings'];

        $this->assertSame(20, $paginator->perPage());
        $this->assertSame(21, $paginator->total());
        $this->assertCount(20, $paginator->items());
        $this->assertTrue($paginator->hasMorePages());
    }

    public function test_returns_meetings_remaining_from_meeting_quota_service(): void
    {
        $student = User::factory()->student()->create();

        $result = app(IndexAction::class)($student);

        $expected = app(MeetingQuotaService::class)
            ->remaining($student);

        $this->assertSame(
            $expected,
            $result['meetingsRemaining'],
        );
    }
}
