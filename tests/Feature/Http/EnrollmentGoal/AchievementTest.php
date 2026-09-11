<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchievementTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_mark_own_goal_as_achieved(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->unachieved()
            ->create();

        $this->actingAs($student)
            ->post(route('enrollment-goals.markAchieved', $goal))
            ->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertNotNull($goal->refresh()->achieved_at);
    }

    public function test_student_can_unmark_own_achieved_goal(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->achieved()
            ->create();

        $this->actingAs($student)
            ->delete(route('enrollment-goals.unmarkAchieved', $goal))
            ->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertNull($goal->refresh()->achieved_at);
    }

    public function test_student_cannot_mark_other_student_goal_as_achieved(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $enrollment = Enrollment::factory()
            ->for($otherStudent)
            ->learning()
            ->create();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->unachieved()
            ->create();

        $this->actingAs($student)
            ->postJson(route('enrollment-goals.markAchieved', $goal))
            ->assertForbidden();

        $this->assertNull($goal->refresh()->achieved_at);
    }

    public function test_student_cannot_unmark_other_student_goal(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $enrollment = Enrollment::factory()
            ->for($otherStudent)
            ->learning()
            ->create();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->achieved()
            ->create();

        $this->actingAs($student)
            ->postJson(route('enrollment-goals.unmarkAchieved', $goal))
            ->assertForbidden();

        $this->assertNotNull($goal->refresh()->achieved_at);
    }

    public function test_student_cannot_mark_goal_as_achieved_when_enrollment_is_passed(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->passed()
            ->create();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->unachieved()
            ->create();

        $this->actingAs($student)
            ->postJson(route('enrollment-goals.markAchieved', $goal))
            ->assertForbidden();

        $this->assertNull($goal->refresh()->achieved_at);
    }

    public function test_student_cannot_unmark_goal_when_enrollment_is_failed(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->failed()
            ->create();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->achieved()
            ->create();

        $this->actingAs($student)
            ->postJson(route('enrollment-goals.unmarkAchieved', $goal))
            ->assertForbidden();

        $this->assertNotNull($goal->refresh()->achieved_at);
    }

    public function test_mark_achieved_fails_when_goal_is_already_achieved(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->achieved()
            ->create();

        // Act & Assert
        $this->actingAs($student)
            ->postJson(route('enrollment-goals.markAchieved', $goal))
            ->assertStatus(409);

        // Assert: 達成済み状態が維持されていること
        $this->assertNotNull($goal->refresh()->achieved_at);
    }

    public function test_unmark_achieved_fails_when_goal_is_not_achieved(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->unachieved()
            ->create();

        // Act & Assert
        $this->actingAs($student)
            ->deleteJson(route('enrollment-goals.unmarkAchieved', $goal))
            ->assertStatus(409);

        // Assert: 未達成状態が維持されていること
        $this->assertNull($goal->refresh()->achieved_at);
    }
}
