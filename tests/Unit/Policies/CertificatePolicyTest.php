<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\User;
use App\Policies\CertificatePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CertificatePolicy の download 認可を検証する Unit テスト。
 * admin は全Certificate / coach は担当Certification / student は本人分のみダウンロード可能。
 */
class CertificatePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_any_certificate(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();
        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();
        $policy = new CertificatePolicy;

        $this->assertTrue($policy->download($admin, $certificate));
    }

    public function test_coach_can_download_certificate_of_assigned_certification(): void
    {
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();
        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();

        $certificate->load('certification.coaches');

        $policy = new CertificatePolicy;

        $this->assertTrue($policy->download($coach, $certificate));
    }

    public function test_coach_cannot_download_certificate_of_unassigned_certification(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();
        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();

        $certificate->load('certification.coaches');

        $policy = new CertificatePolicy;

        $this->assertFalse($policy->download($coach, $certificate));
    }

    public function test_student_can_download_own_certificate(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();
        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();
        $policy = new CertificatePolicy;

        $this->assertTrue($policy->download($student, $certificate));
    }

    public function test_graduated_student_can_download_own_certificate(): void
    {
        $student = User::factory()->student()->graduated()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();
        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();
        $policy = new CertificatePolicy;

        $this->assertTrue($policy->download($student, $certificate));
    }

    public function test_student_cannot_download_other_student_certificate(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($otherStudent)
            ->for($certification)
            ->passed()
            ->create();
        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();
        $policy = new CertificatePolicy;

        $this->assertFalse($policy->download($student, $certificate));
    }
}
