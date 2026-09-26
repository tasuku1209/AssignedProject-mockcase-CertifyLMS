<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Models\User;
use App\Services\Learning\LearningProgressService;
use App\Services\Learning\ProgressSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarize_returns_section_chapter_and_part_progress(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $certification = $enrollment->certification;

        // Part 1: Chapter 1 は全 Section 完了、Chapter 2 は一部未完了
        $part1 = Part::factory()
            ->forCertification($certification)
            ->published()
            ->create();

        $chapter1 = Chapter::factory()
            ->forPart($part1)
            ->published()
            ->create();

        $section11 = Section::factory()
            ->forChapter($chapter1)
            ->published()
            ->create();

        $section12 = Section::factory()
            ->forChapter($chapter1)
            ->published()
            ->create();

        $chapter2 = Chapter::factory()
            ->forPart($part1)
            ->published()
            ->create();

        $section21 = Section::factory()
            ->forChapter($chapter2)
            ->published()
            ->create();

        $section22 = Section::factory()
            ->forChapter($chapter2)
            ->published()
            ->create();

        // Chapter 1 の2 Sectionは完了
        SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($section11)
            ->create();

        SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($section12)
            ->create();

        // Chapter 2 は1 Sectionのみ完了
        SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($section21)
            ->create();

        // Part 2: Chapter 1 の Section は全て完了
        // → Part 2 は完了扱い
        $part2 = Part::factory()
            ->forCertification($certification)
            ->published()
            ->create();

        $chapter3 = Chapter::factory()
            ->forPart($part2)
            ->published()
            ->create();

        $section31 = Section::factory()
            ->forChapter($chapter3)
            ->published()
            ->create();

        $section32 = Section::factory()
            ->forChapter($chapter3)
            ->published()
            ->create();

        SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($section31)
            ->create();

        SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($section32)
            ->create();

        // Act
        $summary = app(LearningProgressService::class)->summarize($enrollment);

        // Assert
        $this->assertInstanceOf(ProgressSummary::class, $summary);

        // Section: 6件中5件完了
        $this->assertSame(6, $summary->sectionsTotal);
        $this->assertSame(5, $summary->sectionsCompleted);
        $this->assertEqualsWithDelta(0.8333, $summary->sectionCompletionRatio, 0.0001);

        // Chapter: 3件中2件完了
        $this->assertSame(3, $summary->chaptersTotal);
        $this->assertSame(2, $summary->chaptersCompleted);
        $this->assertEqualsWithDelta(0.6667, $summary->chapterCompletionRatio, 0.0001);

        // Part: 2件中1件完了
        $this->assertSame(2, $summary->partsTotal);
        $this->assertSame(1, $summary->partsCompleted);
        $this->assertEqualsWithDelta(0.5, $summary->partCompletionRatio, 0.0001);

        // 全体進捗は Section 単位の完了率
        $this->assertEqualsWithDelta(0.8333, $summary->overallCompletionRatio, 0.0001);
    }

    public function test_summarize_excludes_unpublished_content_from_progress(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $certification = $enrollment->certification;

        $publishedPart = Part::factory()
            ->forCertification($certification)
            ->published()
            ->create();

        $publishedChapter = Chapter::factory()
            ->forPart($publishedPart)
            ->published()
            ->create();

        $publishedSection = Section::factory()
            ->forChapter($publishedChapter)
            ->published()
            ->create();

        SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($publishedSection)
            ->create();

        // Draft は集計対象外
        $draftPart = Part::factory()
            ->forCertification($certification)
            ->draft()
            ->create();

        $draftChapter = Chapter::factory()
            ->forPart($draftPart)
            ->draft()
            ->create();

        Section::factory()
            ->forChapter($draftChapter)
            ->draft()
            ->create();

        // Act
        $summary = app(LearningProgressService::class)->summarize($enrollment);

        // Assert
        $this->assertSame(1, $summary->sectionsTotal);
        $this->assertSame(1, $summary->sectionsCompleted);

        $this->assertSame(1, $summary->chaptersTotal);
        $this->assertSame(1, $summary->chaptersCompleted);

        $this->assertSame(1, $summary->partsTotal);
        $this->assertSame(1, $summary->partsCompleted);

        $this->assertEqualsWithDelta(1.0, $summary->sectionCompletionRatio, 0.0001);
        $this->assertEqualsWithDelta(1.0, $summary->chapterCompletionRatio, 0.0001);
        $this->assertEqualsWithDelta(1.0, $summary->partCompletionRatio, 0.0001);
        $this->assertEqualsWithDelta(1.0, $summary->overallCompletionRatio, 0.0001);
    }

    public function test_summarize_returns_zero_ratios_when_no_published_content_exists(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();

        // Act
        $summary = app(LearningProgressService::class)->summarize($enrollment);

        // Assert
        $this->assertInstanceOf(ProgressSummary::class, $summary);

        $this->assertSame(0, $summary->sectionsTotal);
        $this->assertSame(0, $summary->sectionsCompleted);
        $this->assertSame(0.0, $summary->sectionCompletionRatio);

        $this->assertSame(0, $summary->chaptersTotal);
        $this->assertSame(0, $summary->chaptersCompleted);
        $this->assertSame(0.0, $summary->chapterCompletionRatio);

        $this->assertSame(0, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.0, $summary->partCompletionRatio);

        $this->assertSame(0.0, $summary->overallCompletionRatio);
    }

    public function test_batch_section_completion_rates_returns_rate_for_each_enrollment(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();

        $firstEnrollment = Enrollment::factory()
            ->for($student)
            ->create();

        $secondEnrollment = Enrollment::factory()
            ->for($student)
            ->create();

        $firstCertification = $firstEnrollment->certification;
        $secondCertification = $secondEnrollment->certification;

        // First enrollment: 2件中1件完了
        $firstPart = Part::factory()
            ->forCertification($firstCertification)
            ->published()
            ->create();

        $firstChapter = Chapter::factory()
            ->forPart($firstPart)
            ->published()
            ->create();

        $firstSection1 = Section::factory()
            ->forChapter($firstChapter)
            ->published()
            ->create();

        Section::factory()
            ->forChapter($firstChapter)
            ->published()
            ->create();

        SectionProgress::factory()
            ->forEnrollment($firstEnrollment)
            ->forSection($firstSection1)
            ->create();

        // Second enrollment: 2件中2件完了
        $secondPart = Part::factory()
            ->forCertification($secondCertification)
            ->published()
            ->create();

        $secondChapter = Chapter::factory()
            ->forPart($secondPart)
            ->published()
            ->create();

        $secondSection1 = Section::factory()
            ->forChapter($secondChapter)
            ->published()
            ->create();

        $secondSection2 = Section::factory()
            ->forChapter($secondChapter)
            ->published()
            ->create();

        SectionProgress::factory()
            ->forEnrollment($secondEnrollment)
            ->forSection($secondSection1)
            ->create();

        SectionProgress::factory()
            ->forEnrollment($secondEnrollment)
            ->forSection($secondSection2)
            ->create();

        $enrollments = Enrollment::query()
            ->whereKey([$firstEnrollment->id, $secondEnrollment->id])
            ->get();

        // Act
        $result = app(LearningProgressService::class)
            ->batchSectionCompletionRates($enrollments);

        // Assert
        $this->assertCount(2, $result);

        $this->assertEqualsWithDelta(
            0.5,
            $result[$firstEnrollment->id],
            0.0001,
        );

        $this->assertEqualsWithDelta(
            1.0,
            $result[$secondEnrollment->id],
            0.0001,
        );
    }

    public function test_batch_section_completion_rates_returns_zero_for_enrollment_without_progress(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $certification = $enrollment->certification;

        $part = Part::factory()
            ->forCertification($certification)
            ->published()
            ->create();

        $chapter = Chapter::factory()
            ->forPart($part)
            ->published()
            ->create();

        Section::factory()
            ->forChapter($chapter)
            ->published()
            ->create();

        $enrollments = Enrollment::query()
            ->whereKey($enrollment->id)
            ->get();

        // Act
        $result = app(LearningProgressService::class)
            ->batchSectionCompletionRates($enrollments);

        // Assert
        $this->assertArrayHasKey($enrollment->id, $result);
        $this->assertSame(0.0, $result[$enrollment->id]);
    }

    public function test_batch_section_completion_rates_returns_zero_when_no_published_sections_exist(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();

        $enrollments = Enrollment::query()
            ->whereKey($enrollment->id)
            ->get();

        // Act
        $result = app(LearningProgressService::class)
            ->batchSectionCompletionRates($enrollments);

        // Assert
        $this->assertArrayHasKey($enrollment->id, $result);
        $this->assertSame(0.0, $result[$enrollment->id]);
    }

    public function test_batch_section_completion_rates_returns_empty_array_for_empty_enrollments(): void
    {
        // Arrange
        $enrollments = Enrollment::query()->whereKey('non-existent')->get();

        // Act
        $result = app(LearningProgressService::class)
            ->batchSectionCompletionRates($enrollments);

        // Assert
        $this->assertSame([], $result);
    }
}
