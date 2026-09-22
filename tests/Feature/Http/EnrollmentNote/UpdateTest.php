<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_returns_note_edit_view_for_note_author(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        $this->assignCoach($coach, $enrollment->certification);

        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forAuthor($coach)
            ->create();

        // Act
        $response = $this->actingAs($coach)->get(
            route('enrollment-notes.edit', $note),
        );

        // Assert
        $response
            ->assertOk()
            ->assertViewIs('enrollment-note.edit')
            ->assertViewHas('note', $note);
    }

    public function test_edit_forbids_other_coach_note(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();

        $enrollment = Enrollment::factory()->create();

        $this->assignCoach($coach, $enrollment->certification);
        $this->assignCoach($otherCoach, $enrollment->certification);

        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forAuthor($otherCoach)
            ->create();

        // Act
        $response = $this->actingAs($coach)->get(
            route('enrollment-notes.edit', $note),
        );

        // Assert
        $response->assertForbidden();
    }

    public function test_update_changes_note_body_and_redirects_with_success_message(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        $this->assignCoach($coach, $enrollment->certification);

        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forAuthor($coach)
            ->create([
                'body' => '更新前のメモです。',
            ]);

        // Act
        $response = $this->actingAs($coach)->patch(
            route('enrollment-notes.update', $note),
            [
                'body' => '更新後のメモです。',
            ],
        );

        // Assert
        $response
            ->assertRedirect(route('enrollments.show', $enrollment))
            ->assertSessionHas('success', 'メモを更新しました。');

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '更新後のメモです。',
        ]);
    }

    public function test_admin_can_update_other_coach_note(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();

        $enrollment = Enrollment::factory()->create();

        $this->assignCoach($coach, $enrollment->certification);

        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forAuthor($coach)
            ->create([
                'body' => 'コーチが作成したメモです。',
            ]);

        // Act
        $response = $this->actingAs($admin)->patch(
            route('enrollment-notes.update', $note),
            [
                'body' => '管理者が更新したメモです。',
            ],
        );

        // Assert
        $response
            ->assertRedirect(route('enrollments.show', $enrollment))
            ->assertSessionHas('success', 'メモを更新しました。');

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'user_id' => $coach->id,
            'body' => '管理者が更新したメモです。',
        ]);
    }

    public function test_update_forbids_other_coach_note(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();

        $enrollment = Enrollment::factory()->create();

        $this->assignCoach($coach, $enrollment->certification);
        $this->assignCoach($otherCoach, $enrollment->certification);

        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forAuthor($otherCoach)
            ->create([
                'body' => '他コーチのメモです。',
            ]);

        // Act
        $response = $this->actingAs($coach)->patch(
            route('enrollment-notes.update', $note),
            [
                'body' => '書き換えてはいけないメモです。',
            ],
        );

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'user_id' => $otherCoach->id,
            'body' => '他コーチのメモです。',
        ]);
    }

    public function test_update_redirects_back_with_validation_error_when_body_exceeds_max_length(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        $this->assignCoach($coach, $enrollment->certification);

        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forAuthor($coach)
            ->create([
                'body' => '更新前のメモです。',
            ]);

        // Act
        $response = $this->actingAs($coach)->patch(
            route('enrollment-notes.update', $note),
            [
                'body' => str_repeat('あ', 2001),
            ],
        );

        // Assert
        $response
            ->assertRedirect()
            ->assertSessionHasErrors('body');

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '更新前のメモです。',
        ]);
    }

    private function assignCoach(
        User $coach,
        Certification $certification,
    ): void {
        $admin = User::factory()->admin()->create();

        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
    }
}
