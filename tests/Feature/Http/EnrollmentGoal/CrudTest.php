<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_goal_for_own_learning_enrollment(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        $this->actingAs($student)
            ->post(route('enrollments.goals.store', $enrollment), [
                'title' => '過去問5年分を解き終える',
                'target_date' => '2026-10-01',
                'description' => '毎日少しずつ進める',
            ])
            ->assertRedirect(route('enrollments.show', $enrollment));

        $goal = EnrollmentGoal::query()
            ->where('enrollment_id', $enrollment->id)
            ->firstOrFail();

        $this->assertSame('過去問5年分を解き終える', $goal->title);
        $this->assertSame('2026-10-01', $goal->target_date?->format('Y-m-d'));
        $this->assertSame('毎日少しずつ進める', $goal->description);
        $this->assertNull($goal->achieved_at);
    }

    public function test_student_can_create_goal_without_optional_fields(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        $this->actingAs($student)
            ->post(route('enrollments.goals.store', $enrollment), [
                'title' => '過去問を解く',
            ])
            ->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'title' => '過去問を解く',
            'target_date' => null,
            'description' => null,
        ]);
    }

    public function test_student_cannot_create_goal_for_other_students_enrollment(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $enrollment = Enrollment::factory()
            ->for($otherStudent)
            ->learning()
            ->create();

        $this->actingAs($student)
            ->postJson(route('enrollments.goals.store', $enrollment), [
                'title' => '他人の目標',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'title' => '他人の目標',
        ]);
    }

    public function test_student_cannot_create_goal_for_passed_enrollment(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->passed()
            ->create();

        $this->actingAs($student)
            ->postJson(route('enrollments.goals.store', $enrollment), [
                'title' => '修了後の目標',
            ])
            ->assertForbidden();
    }

    public function test_student_cannot_create_goal_for_failed_enrollment(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->failed()
            ->create();

        $this->actingAs($student)
            ->postJson(route('enrollments.goals.store', $enrollment), [
                'title' => '失敗後の目標',
            ])
            ->assertForbidden();
    }

    public function test_student_can_edit_own_goal(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();
        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();

        $this->actingAs($student)
            ->get(route('enrollment-goals.edit', $goal))
            ->assertOk()
            ->assertViewIs('enrollment-goal.edit')
            ->assertViewHas('goal', $goal);
    }

    public function test_student_can_update_own_goal(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();
        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create([
                'title' => '変更前の目標',
            ]);

        $this->actingAs($student)
            ->patch(route('enrollment-goals.update', $goal), [
                'title' => '変更後の目標',
                'target_date' => '2026-11-01',
                'description' => '変更後の詳細',
            ])
            ->assertRedirect(route('enrollments.show', $enrollment));

        $goal->refresh();

        $this->assertSame('変更後の目標', $goal->title);
        $this->assertSame('2026-11-01', $goal->target_date?->format('Y-m-d'));
        $this->assertSame('変更後の詳細', $goal->description);
    }

    public function test_student_cannot_edit_other_students_goal(): void
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

        $this->actingAs($student)
            ->get(route('enrollment-goals.edit', $goal))
            ->assertForbidden();
    }

    public function test_student_cannot_update_other_students_goal(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $enrollment = Enrollment::factory()
            ->for($otherStudent)
            ->learning()
            ->create();
        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create([
                'title' => '元の目標',
            ]);

        $this->actingAs($student)
            ->patchJson(route('enrollment-goals.update', $goal), [
                'title' => '不正な変更',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'title' => '元の目標',
        ]);
    }

    public function test_student_cannot_update_goal_for_passed_enrollment(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->passed()
            ->create();
        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create([
                'title' => '元の目標',
            ]);

        $this->actingAs($student)
            ->patchJson(route('enrollment-goals.update', $goal), [
                'title' => '変更しようとした目標',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'title' => '元の目標',
        ]);
    }

    public function test_student_cannot_update_goal_for_failed_enrollment(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->failed()
            ->create();
        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create([
                'title' => '元の目標',
            ]);

        $this->actingAs($student)
            ->patchJson(route('enrollment-goals.update', $goal), [
                'title' => '変更しようとした目標',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'title' => '元の目標',
        ]);
    }

    public function test_student_can_delete_own_goal(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();
        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();

        $this->actingAs($student)
            ->delete(route('enrollment-goals.destroy', $goal))
            ->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseMissing('enrollment_goals', [
            'id' => $goal->id,
        ]);
    }

    public function test_student_cannot_delete_other_students_goal(): void
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

        $this->actingAs($student)
            ->deleteJson(route('enrollment-goals.destroy', $goal))
            ->assertForbidden();

        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
        ]);
    }

    public function test_student_cannot_delete_goal_for_passed_enrollment(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->passed()
            ->create();
        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();

        $this->actingAs($student)
            ->deleteJson(route('enrollment-goals.destroy', $goal))
            ->assertForbidden();

        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
        ]);
    }

    public function test_student_cannot_delete_goal_for_failed_enrollment(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->failed()
            ->create();
        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();

        $this->actingAs($student)
            ->deleteJson(route('enrollment-goals.destroy', $goal))
            ->assertForbidden();

        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
        ]);
    }
}
