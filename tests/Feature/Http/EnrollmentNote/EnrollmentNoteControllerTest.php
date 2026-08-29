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

/**
 * EnrollmentNoteController の HTTP 統合テスト。
 *
 * store / edit / update / destroy の代表的な正常系と、
 * コーチの担当資格・メモ作成者による認可境界を検証する。
 */
class EnrollmentNoteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_note_for_assigned_coach(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        $this->assignCoach($coach, $enrollment->certification);

        // Act
        $response = $this->actingAs($coach)->post(
            route('enrollments.notes.store', $enrollment),
            [
                'body' => '受講状況は順調です。',
            ],
        );

        // Assert
        $response->assertRedirect(
            route('enrollments.show', $enrollment)
        );

        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'user_id' => $coach->id,
            'body' => '受講状況は順調です。',
        ]);
    }

    public function test_store_creates_note_for_admin(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->create();

        // Act
        $response = $this->actingAs($admin)->post(
            route('enrollments.notes.store', $enrollment),
            [
                'body' => '管理者からの確認メモです。',
            ],
        );

        // Assert
        $response->assertRedirect(
            route('enrollments.show', $enrollment)
        );

        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'user_id' => $admin->id,
            'body' => '管理者からの確認メモです。',
        ]);
    }

    public function test_store_forbids_unassigned_coach(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        // Act
        $response = $this->actingAs($coach)->postJson(
            route('enrollments.notes.store', $enrollment),
            [
                'body' => '担当外資格へのメモです。',
            ],
        );

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseMissing('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'user_id' => $coach->id,
        ]);
    }

    public function test_edit_returns_note_edit_view_for_note_author(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        $this->assignCoach($coach, $enrollment->certification);

        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forCoach($coach)
            ->create();

        // Act
        $response = $this->actingAs($coach)->get(
            route('enrollment-notes.edit', $note)
        );

        // Assert
        $response->assertOk();
        $response->assertViewIs('enrollment-note.edit');
        $response->assertViewHas('note', $note);
    }

    public function test_edit_forbids_other_coach_note(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        $this->assignCoach($coach, $enrollment->certification);

        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forCoach($otherCoach)
            ->create();

        // Act
        $response = $this->actingAs($coach)->getJson(
            route('enrollment-notes.edit', $note)
        );

        // Assert
        $response->assertForbidden();
    }

    public function test_update_changes_note_body_for_note_author(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        $this->assignCoach($coach, $enrollment->certification);

        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forCoach($coach)
            ->create([
                'body' => '変更前のメモです。',
            ]);

        // Act
        $response = $this->actingAs($coach)->patch(
            route('enrollment-notes.update', $note),
            [
                'body' => '変更後のメモです。',
            ],
        );

        // Assert
        $response->assertRedirect(
            route('enrollments.show', $enrollment)
        );

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '変更後のメモです。',
        ]);
    }

    public function test_update_forbids_other_coach_note(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        $this->assignCoach($coach, $enrollment->certification);

        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forCoach($otherCoach)
            ->create([
                'body' => '変更してはいけないメモです。',
            ]);

        // Act
        $response = $this->actingAs($coach)->patchJson(
            route('enrollment-notes.update', $note),
            [
                'body' => '不正な変更です。',
            ],
        );

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '変更してはいけないメモです。',
        ]);
    }

    public function test_destroy_deletes_own_note(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        $this->assignCoach($coach, $enrollment->certification);

        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forCoach($coach)
            ->create();

        // Act
        $response = $this->actingAs($coach)->delete(
            route('enrollment-notes.destroy', $note)
        );

        // Assert
        $response->assertRedirect(
            route('enrollments.show', $enrollment)
        );

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

        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forCoach($otherCoach)
            ->create();

        // Act
        $response = $this->actingAs($coach)->deleteJson(
            route('enrollment-notes.destroy', $note)
        );

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
        ]);
    }

    public function test_admin_can_update_other_coach_note(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forCoach($coach)
            ->create([
                'body' => 'コーチが作成したメモです。',
            ]);

        // Act
        $response = $this->actingAs($admin)->patch(
            route('enrollment-notes.update', $note),
            [
                'body' => '管理者による修正です。',
            ],
        );

        // Assert
        $response->assertRedirect(
            route('enrollments.show', $enrollment)
        );

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '管理者による修正です。',
        ]);
    }

    public function test_student_cannot_create_note(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();

        // Act
        $response = $this->actingAs($student)->postJson(
            route('enrollments.notes.store', $enrollment),
            [
                'body' => '学生による不正なメモです。',
            ],
        );

        // Assert
        $response->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();

        // Act
        $response = $this->post(
            route('enrollments.notes.store', $enrollment),
            [
                'body' => '未認証ユーザーのメモです。',
            ],
        );

        // Assert
        $response->assertRedirect(route('login'));
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
