<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EnrollmentNote モデルのリレーションを検証する Unit テスト。
 *
 * enrollment / author の主要 2 リレーションを確認する。
 */
class EnrollmentNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_relation_returns_parent_enrollment(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->create();

        // Act
        $parent = $note->enrollment;

        // Assert
        $this->assertTrue($parent->is($enrollment));
    }

    public function test_author_relation_returns_note_creator(): void
    {
        // Arrange
        $author = User::factory()->coach()->create();
        $note = EnrollmentNote::factory()
            ->forAuthor($author)
            ->create();

        // Act
        $parent = $note->author;

        // Assert
        $this->assertTrue($parent->is($author));
    }
}
