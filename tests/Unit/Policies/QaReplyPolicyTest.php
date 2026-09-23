<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Policies\QaReplyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QaReplyPolicyTest extends TestCase
{
    use RefreshDatabase;

    private QaReplyPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new QaReplyPolicy;
    }

    public function test_student_can_create_reply_to_published_certification_thread(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()
            ->forCertification($certification)
            ->create();

        $this->assertTrue(
            $this->policy->create($student, $thread)
        );
    }

    public function test_student_cannot_create_reply_to_unpublished_certification_thread(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->create([
            'status' => CertificationStatus::Draft->value,
        ]);
        $thread = QaThread::factory()
            ->forCertification($certification)
            ->create();

        $this->assertFalse(
            $this->policy->create($student, $thread)
        );
    }

    public function test_assigned_coach_can_create_reply(): void
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

        $this->assertTrue(
            $this->policy->create($coach, $thread)
        );
    }

    public function test_unassigned_coach_cannot_create_reply(): void
    {
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()
            ->forCertification($certification)
            ->create();

        $this->assertFalse(
            $this->policy->create($coach, $thread)
        );
    }

    public function test_assigned_coach_cannot_create_reply_to_thread_of_unpublished_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();

        $certification = Certification::factory()
            ->draft()
            ->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $thread = QaThread::factory()
            ->forCertification($certification)
            ->create();

        $this->assertFalse(
            $this->policy->create($coach, $thread)
        );
    }

    public function test_admin_cannot_create_reply(): void
    {
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()
            ->forCertification($certification)
            ->create();

        $this->assertFalse(
            $this->policy->create($admin, $thread)
        );
    }

    public function test_student_can_update_own_reply(): void
    {
        $student = User::factory()->student()->create();
        $reply = QaReply::factory()
            ->forUser($student)
            ->create();

        $this->assertTrue(
            $this->policy->update($student, $reply)
        );
    }

    public function test_coach_can_update_own_reply(): void
    {
        $coach = User::factory()->coach()->create();
        $reply = QaReply::factory()
            ->forUser($coach)
            ->create();

        $this->assertTrue(
            $this->policy->update($coach, $reply)
        );
    }

    public function test_student_cannot_update_other_users_reply(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $reply = QaReply::factory()
            ->forUser($otherStudent)
            ->create();

        $this->assertFalse(
            $this->policy->update($student, $reply)
        );
    }

    public function test_coach_cannot_update_other_users_reply(): void
    {
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();

        $reply = QaReply::factory()
            ->forUser($otherCoach)
            ->create();

        $this->assertFalse(
            $this->policy->update($coach, $reply)
        );
    }

    public function test_admin_cannot_update_reply(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $reply = QaReply::factory()
            ->forUser($student)
            ->create();

        $this->assertFalse(
            $this->policy->update($admin, $reply)
        );
    }

    public function test_admin_can_delete_any_reply(): void
    {
        $admin = User::factory()->admin()->create();
        $reply = QaReply::factory()->create();

        $this->assertTrue(
            $this->policy->delete($admin, $reply)
        );
    }

    public function test_student_can_delete_own_reply(): void
    {
        $student = User::factory()->student()->create();
        $reply = QaReply::factory()
            ->forUser($student)
            ->create();

        $this->assertTrue(
            $this->policy->delete($student, $reply)
        );
    }

    public function test_coach_can_delete_own_reply(): void
    {
        $coach = User::factory()->coach()->create();
        $reply = QaReply::factory()
            ->forUser($coach)
            ->create();

        $this->assertTrue(
            $this->policy->delete($coach, $reply)
        );
    }

    public function test_student_cannot_delete_other_users_reply(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $reply = QaReply::factory()
            ->forUser($otherStudent)
            ->create();

        $this->assertFalse(
            $this->policy->delete($student, $reply)
        );
    }

    public function test_coach_cannot_delete_other_users_reply(): void
    {
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();

        $reply = QaReply::factory()
            ->forUser($otherCoach)
            ->create();

        $this->assertFalse(
            $this->policy->delete($coach, $reply)
        );
    }
}
