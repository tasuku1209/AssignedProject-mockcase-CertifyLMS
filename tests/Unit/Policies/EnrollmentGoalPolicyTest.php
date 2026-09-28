<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use App\Policies\EnrollmentGoalPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentGoalPolicyTest extends TestCase
{
    use RefreshDatabase;

    private EnrollmentGoalPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new EnrollmentGoalPolicy;
    }

    public function test_learning_student_can_create_goal_for_own_enrollment(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        $this->assertTrue(
            $this->policy->create($student, $enrollment),
        );
    }

    public function test_student_cannot_create_goal_for_another_students_enrollment(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $enrollment = Enrollment::factory()
            ->for($otherStudent)
            ->learning()
            ->create();

        $this->assertFalse(
            $this->policy->create($student, $enrollment),
        );
    }

    public function test_passed_student_cannot_create_goal(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->passed()
            ->create();

        $this->assertFalse(
            $this->policy->create($student, $enrollment),
        );
    }

    public function test_failed_student_cannot_create_goal(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->failed()
            ->create();

        $this->assertFalse(
            $this->policy->create($student, $enrollment),
        );
    }

    public function test_admin_cannot_create_goal(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()
            ->learning()
            ->create();

        $this->assertFalse(
            $this->policy->create($admin, $enrollment),
        );
    }

    public function test_coach_cannot_create_goal(): void
    {
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()
            ->learning()
            ->create();

        $this->assertFalse(
            $this->policy->create($coach, $enrollment),
        );
    }

    public function test_learning_student_can_manage_own_goal(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();
        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();

        $this->assertTrue($this->policy->update($student, $goal));
        $this->assertTrue($this->policy->delete($student, $goal));
        $this->assertTrue($this->policy->markAchieved($student, $goal));
        $this->assertTrue($this->policy->unmarkAchieved($student, $goal));
    }

    public function test_student_cannot_manage_another_students_goal(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $enrollment = Enrollment::factory()
            ->for($otherStudent)
            ->learning()
            ->create();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();

        $this->assertFalse($this->policy->update($student, $goal));
        $this->assertFalse($this->policy->delete($student, $goal));
        $this->assertFalse($this->policy->markAchieved($student, $goal));
        $this->assertFalse($this->policy->unmarkAchieved($student, $goal));
    }

    public function test_passed_student_cannot_manage_own_goal(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->passed()
            ->create();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();

        $this->assertFalse($this->policy->update($student, $goal));
        $this->assertFalse($this->policy->delete($student, $goal));
        $this->assertFalse($this->policy->markAchieved($student, $goal));
        $this->assertFalse($this->policy->unmarkAchieved($student, $goal));
    }

    public function test_failed_student_cannot_manage_own_goal(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->failed()
            ->create();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();

        $this->assertFalse($this->policy->update($student, $goal));
        $this->assertFalse($this->policy->delete($student, $goal));
        $this->assertFalse($this->policy->markAchieved($student, $goal));
        $this->assertFalse($this->policy->unmarkAchieved($student, $goal));
    }

    public function test_admin_cannot_manage_student_goal(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();

        $this->assertFalse($this->policy->update($admin, $goal));
        $this->assertFalse($this->policy->delete($admin, $goal));
        $this->assertFalse($this->policy->markAchieved($admin, $goal));
        $this->assertFalse($this->policy->unmarkAchieved($admin, $goal));
    }

    public function test_coach_cannot_manage_student_goal(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();

        $this->assertFalse($this->policy->update($coach, $goal));
        $this->assertFalse($this->policy->delete($coach, $goal));
        $this->assertFalse($this->policy->markAchieved($coach, $goal));
        $this->assertFalse($this->policy->unmarkAchieved($coach, $goal));
    }
}
