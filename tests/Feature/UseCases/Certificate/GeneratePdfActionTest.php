<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Certificate;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\UseCases\Certificate\GeneratePdfAction;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class GeneratePdfActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_and_stores_pdf(): void
    {
        Storage::fake('private');

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

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

        app(GeneratePdfAction::class)($certificate);

        Storage::disk('private')->assertExists(
            $certificate->pdf_path,
        );
    }

    public function test_throws_exception_when_pdf_cannot_be_saved(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

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

        $disk = Mockery::mock(Filesystem::class);

        $disk->shouldReceive('put')
            ->once()
            ->withArgs(function (string $path, string $contents) use ($certificate): bool {
                return $path === $certificate->pdf_path
                    && $contents !== '';
            })
            ->andReturn(false);

        Storage::shouldReceive('disk')
            ->once()
            ->with('private')
            ->andReturn($disk);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('修了証PDFの保存に失敗しました。');

        app(GeneratePdfAction::class)($certificate);
    }
}
