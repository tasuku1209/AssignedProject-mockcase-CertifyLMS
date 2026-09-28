<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\ShowAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_loads_required_relations(): void
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
            ->create();

        // Act
        $result = app(ShowAction::class)($meeting);

        // Assert
        $this->assertTrue($result->relationLoaded('enrollment'));
        $this->assertTrue(
            $result->enrollment->relationLoaded('certification'),
        );
        $this->assertTrue($result->relationLoaded('coach'));
        $this->assertTrue($result->relationLoaded('student'));
        $this->assertTrue($result->relationLoaded('canceledBy'));
        $this->assertTrue($result->relationLoaded('meetingMemo'));
    }
}
