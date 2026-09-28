<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\MeetingAvailabilityService;
use App\UseCases\Meeting\FetchAvailabilityAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class FetchAvailabilityActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_available_slots_for_enrollment_certification(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $date = Carbon::parse('2026-09-07');

        $slots = collect([
            [
                'slot_start' => $date->copy()->setTime(9, 0),
                'slot_end' => $date->copy()->setTime(10, 0),
                'available_coach_count' => 2,
            ],
            [
                'slot_start' => $date->copy()->setTime(10, 0),
                'slot_end' => $date->copy()->setTime(11, 0),
                'available_coach_count' => 1,
            ],
        ]);

        $mock = Mockery::mock(MeetingAvailabilityService::class);
        $mock->shouldReceive('slotsForCertification')
            ->once()
            ->with(
                Mockery::on(
                    fn (Certification $actual) => $actual->is($certification)
                ),
                $date,
            )
            ->andReturn($slots);

        $this->app->instance(MeetingAvailabilityService::class, $mock);

        $action = app(FetchAvailabilityAction::class);

        // Act
        $result = $action($enrollment, $date);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertSame($slots, $result);
        $this->assertCount(2, $result);
    }
}
