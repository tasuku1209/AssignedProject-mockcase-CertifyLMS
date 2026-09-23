<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_note_and_redirects_with_success_message(): void
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
        $response
            ->assertRedirect(route('enrollments.show', $enrollment))
            ->assertSessionHas('success', 'メモを追加しました。');

        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'user_id' => $coach->id,
            'body' => '受講状況は順調です。',
        ]);
    }

    public function test_store_forbids_unassigned_coach(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        // Act
        $response = $this->actingAs($coach)->post(
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

    public function test_store_redirects_back_with_validation_error_when_body_exceeds_max_length(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        $this->assignCoach($coach, $enrollment->certification);

        // Act
        $response = $this->actingAs($coach)->post(
            route('enrollments.notes.store', $enrollment),
            [
                'body' => str_repeat('あ', 2001),
            ],
        );

        // Assert
        $response
            ->assertRedirect()
            ->assertSessionHasErrors('body');
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
