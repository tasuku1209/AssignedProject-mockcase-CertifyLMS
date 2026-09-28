<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Certificate;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateDownloadControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_download_own_certificate(): void
    {
        Storage::fake('private');

        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();
        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create([
                'pdf_path' => 'certificates/test-certificate.pdf',
            ]);

        Storage::disk('private')->put(
            $certificate->pdf_path,
            '%PDF-1.4 test certificate',
        );

        $response = $this->actingAs($student)
            ->get(route('certificates.download', $certificate));

        $response->assertOk();
        $response->assertDownload('test-certificate.pdf');

        Storage::disk('private')->assertExists($certificate->pdf_path);
    }

    public function test_returns_404_when_certificate_pdf_does_not_exist(): void
    {
        Storage::fake('private');

        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();
        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create([
                'pdf_path' => 'certificates/missing.pdf',
            ]);

        $response = $this->actingAs($student)
            ->get(route('certificates.download', $certificate));

        $response->assertNotFound();
    }

    public function test_returns_403_when_student_downloads_other_student_certificate(): void
    {
        Storage::fake('private');

        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($otherStudent)
            ->for($certification)
            ->passed()
            ->create();
        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create([
                'pdf_path' => 'certificates/other-student.pdf',
            ]);

        Storage::disk('private')->put(
            $certificate->pdf_path,
            '%PDF-1.4 other student certificate',
        );

        $response = $this->actingAs($student)
            ->get(route('certificates.download', $certificate));

        $response->assertForbidden();
    }
}
