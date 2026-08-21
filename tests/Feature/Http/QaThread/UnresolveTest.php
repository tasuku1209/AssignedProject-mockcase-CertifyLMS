<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnresolveTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_unresolve_own_thread(): void
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
            ->post(route('qa-board.unresolve', $thread));

        // Assert
        $response->assertRedirect(route('qa-board.show', $thread));

        $this->assertSame(
            QaThreadStatus::Unresolved,
            $thread->fresh()->status
        );

        $this->assertNull($thread->fresh()->resolved_at);
    }

    public function test_cannot_unresolve_already_unresolved_thread(): void
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
            ->postJson(route('qa-board.unresolve', $thread));

        // Assert
        $response->assertStatus(409);

        $this->assertSame(
            QaThreadStatus::Unresolved,
            $thread->fresh()->status
        );

        $this->assertNull($thread->fresh()->resolved_at);
    }

    public function test_other_student_cannot_unresolve_thread(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $thread = QaThread::factory()
            ->forUser($student)
            ->create([
                'status' => QaThreadStatus::Resolved->value,
                'resolved_at' => now(),
            ]);

        // Act
        $response = $this->actingAs($otherStudent)
            ->post(route('qa-board.unresolve', $thread));

        // Assert
        $response->assertForbidden();

        $this->assertSame(
            QaThreadStatus::Resolved,
            $thread->fresh()->status
        );
    }

    public function test_coach_cannot_unresolve_thread(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $thread = QaThread::factory()
            ->forUser($student)
            ->create([
                'status' => QaThreadStatus::Resolved->value,
                'resolved_at' => now(),
            ]);

        // Act
        $response = $this->actingAs($coach)
            ->post(route('qa-board.unresolve', $thread));

        // Assert
        $response->assertForbidden();

        $this->assertSame(
            QaThreadStatus::Resolved,
            $thread->fresh()->status
        );
    }
}
