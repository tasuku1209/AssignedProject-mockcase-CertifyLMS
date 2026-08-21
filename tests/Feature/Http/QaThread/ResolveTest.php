<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_resolve_own_thread(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $thread = QaThread::factory()
            ->forUser($student)
            ->create([
                'status' => QaThreadStatus::Unresolved->value,
                'resolved_at' => null,
            ]);

        // Act
        $response = $this->actingAs($student)
            ->post(route('qa-board.resolve', $thread));

        // Assert
        $response->assertRedirect(route('qa-board.show', $thread));

        $this->assertSame(
            QaThreadStatus::Resolved,
            $thread->fresh()->status
        );

        $this->assertNotNull($thread->fresh()->resolved_at);
    }

    public function test_cannot_resolve_already_resolved_thread(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $thread = QaThread::factory()
            ->forUser($student)
            ->create([
                'status' => QaThreadStatus::Resolved->value,
                'resolved_at' => now(),
            ]);

        // Act
        $response = $this->actingAs($student)
            ->postJson(route('qa-board.resolve', $thread));

        // Assert
        $response->assertStatus(409);

        $this->assertSame(
            QaThreadStatus::Resolved,
            $thread->fresh()->status
        );
    }

    public function test_other_student_cannot_resolve_thread(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $thread = QaThread::factory()
            ->forUser($student)
            ->create([
                'status' => QaThreadStatus::Unresolved->value,
            ]);

        // Act
        $response = $this->actingAs($otherStudent)
            ->post(route('qa-board.resolve', $thread));

        // Assert
        $response->assertForbidden();

        $this->assertSame(
            QaThreadStatus::Unresolved,
            $thread->fresh()->status
        );
    }

    public function test_coach_cannot_resolve_thread(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $thread = QaThread::factory()
            ->forUser($student)
            ->create([
                'status' => QaThreadStatus::Unresolved->value,
            ]);

        // Act
        $response = $this->actingAs($coach)
            ->post(route('qa-board.resolve', $thread));

        // Assert
        $response->assertForbidden();

        $this->assertSame(
            QaThreadStatus::Unresolved,
            $thread->fresh()->status
        );
    }
}
