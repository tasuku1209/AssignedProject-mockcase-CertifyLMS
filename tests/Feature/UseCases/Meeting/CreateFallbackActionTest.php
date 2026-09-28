<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\UseCases\Meeting\CreateFallbackAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateFallbackActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_learning_and_passed_enrollments(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $learning = Enrollment::factory()
            ->for($student)
            ->create([
                'status' => EnrollmentStatus::Learning->value,
            ]);

        $passed = Enrollment::factory()
            ->for($student)
            ->create([
                'status' => EnrollmentStatus::Passed->value,
            ]);

        $failed = Enrollment::factory()
            ->for($student)
            ->create([
                'status' => EnrollmentStatus::Failed->value,
            ]);

        // Act
        $result = app(CreateFallbackAction::class)($student);

        // Assert
        $this->assertSame(
            [$learning->id, $passed->id],
            $result->pluck('id')->all(),
        );

        $this->assertNotContains(
            $failed->id,
            $result->pluck('id')->all(),
        );
    }

    public function test_returns_only_enrollments_belonging_to_the_given_user(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $ownEnrollment = Enrollment::factory()
            ->for($student)
            ->create([
                'status' => EnrollmentStatus::Learning->value,
            ]);

        $otherEnrollment = Enrollment::factory()
            ->for($otherStudent)
            ->create([
                'status' => EnrollmentStatus::Learning->value,
            ]);

        // Act
        $result = app(CreateFallbackAction::class)($student);

        // Assert
        $this->assertSame(
            [$ownEnrollment->id],
            $result->pluck('id')->all(),
        );

        $this->assertNotContains(
            $otherEnrollment->id,
            $result->pluck('id')->all(),
        );
    }

    public function test_loads_certification_relation(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->create();

        Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->create([
                'status' => EnrollmentStatus::Learning->value,
            ]);

        // Act
        $result = app(CreateFallbackAction::class)($student);

        // Assert
        $enrollment = $result->first();

        $this->assertTrue(
            $enrollment->relationLoaded('certification'),
        );
    }
}
