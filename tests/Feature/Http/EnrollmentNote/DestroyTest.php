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

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_destroy_deletes_own_note_and_redirects_with_success_message(): void
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
        $response = $this->actingAs($coach)->delete(
            route('enrollment-notes.destroy', $note),
        );

        // Assert
        $response
            ->assertRedirect(route('enrollments.show', $enrollment))
            ->assertSessionHas('success', 'メモを削除しました。');

        $this->assertDatabaseMissing('enrollment_notes', [
            'id' => $note->id,
        ]);
    }

    public function test_admin_can_delete_other_coach_note(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();

        $enrollment = Enrollment::factory()->create();

        $this->assignCoach($coach, $enrollment->certification);

        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forAuthor($coach)
            ->create();

        // Act
        $response = $this->actingAs($admin)->delete(
            route('enrollment-notes.destroy', $note),
        );

        // Assert
        $response
            ->assertRedirect(route('enrollments.show', $enrollment))
            ->assertSessionHas('success', 'メモを削除しました。');

        $this->assertDatabaseMissing('enrollment_notes', [
            'id' => $note->id,
        ]);
    }

    public function test_destroy_forbids_other_coach_note(): void
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
        $response = $this->actingAs($coach)->delete(
            route('enrollment-notes.destroy', $note),
        );

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'user_id' => $otherCoach->id,
            'body' => '他コーチのメモです。',
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
