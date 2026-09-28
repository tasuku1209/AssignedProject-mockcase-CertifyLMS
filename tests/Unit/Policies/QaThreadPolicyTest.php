<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use App\Policies\QaThreadPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QaThreadPolicyTest extends TestCase
{
    use RefreshDatabase;

    private QaThreadPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new QaThreadPolicy;
    }

    public function test_admin_can_view_any_thread(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($this->policy->viewAny($admin));
    }

    public function test_coach_can_view_any_thread(): void
    {
        $coach = User::factory()->coach()->create();

        $this->assertTrue($this->policy->viewAny($coach));
    }

    public function test_student_can_view_any_thread(): void
    {
        $student = User::factory()->student()->create();

        $this->assertTrue($this->policy->viewAny($student));
    }

    public function test_admin_can_view_any_thread_regardless_of_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->create();
        $thread = QaThread::factory()
            ->forCertification($certification)
            ->create();

        $this->assertTrue($this->policy->view($admin, $thread));
    }

    public function test_assigned_coach_can_view_thread(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $thread = QaThread::factory()
            ->forCertification($certification)
            ->create();

        $this->assertTrue($this->policy->view($coach, $thread));
    }

    public function test_unassigned_coach_cannot_view_thread(): void
    {
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()
            ->forCertification($certification)
            ->create();

        $this->assertFalse($this->policy->view($coach, $thread));
    }

    public function test_assigned_coach_cannot_view_thread_of_draft_certification(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->draft()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $thread = QaThread::factory()
            ->forCertification($certification)
            ->create();

        // Act & Assert
        $this->assertFalse($this->policy->view($coach, $thread));
    }

    public function test_student_can_view_thread_for_published_certification(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()
            ->forCertification($certification)
            ->create();

        $this->assertTrue($this->policy->view($student, $thread));
    }

    public function test_student_cannot_view_thread_for_unpublished_certification(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->create([
            'status' => CertificationStatus::Draft->value,
        ]);

        $thread = QaThread::factory()
            ->forCertification($certification)
            ->create();

        $this->assertFalse($this->policy->view($student, $thread));
    }

    public function test_only_student_can_create_thread(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue($this->policy->create($student));
        $this->assertFalse($this->policy->create($coach));
        $this->assertFalse($this->policy->create($admin));
    }

    public function test_student_can_update_own_thread(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()
            ->forUser($student)
            ->create();

        $this->assertTrue($this->policy->update($student, $thread));
    }

    public function test_student_cannot_update_other_users_thread(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $thread = QaThread::factory()
            ->forUser($otherStudent)
            ->create();

        $this->assertFalse($this->policy->update($student, $thread));
    }

    public function test_admin_can_delete_any_thread(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();

        $this->assertTrue($this->policy->delete($admin, $thread));
    }

    public function test_student_can_delete_own_thread(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()
            ->forUser($student)
            ->create();

        $this->assertTrue($this->policy->delete($student, $thread));
    }

    public function test_student_cannot_delete_other_users_thread(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $thread = QaThread::factory()
            ->forUser($otherStudent)
            ->create();

        $this->assertFalse($this->policy->delete($student, $thread));
    }

    public function test_coach_cannot_delete_thread(): void
    {
        $coach = User::factory()->coach()->create();
        $thread = QaThread::factory()->create();

        $this->assertFalse($this->policy->delete($coach, $thread));
    }

    public function test_student_can_resolve_own_thread(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()
            ->forUser($student)
            ->create();

        $this->assertTrue($this->policy->resolve($student, $thread));
    }

    public function test_student_cannot_resolve_other_users_thread(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $thread = QaThread::factory()
            ->forUser($otherStudent)
            ->create();

        $this->assertFalse($this->policy->resolve($student, $thread));
    }

    public function test_student_can_unresolve_own_thread(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()
            ->forUser($student)
            ->create();

        $this->assertTrue($this->policy->unresolve($student, $thread));
    }

    public function test_student_cannot_unresolve_other_users_thread(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $thread = QaThread::factory()
            ->forUser($otherStudent)
            ->create();

        $this->assertFalse($this->policy->unresolve($student, $thread));
    }
}
