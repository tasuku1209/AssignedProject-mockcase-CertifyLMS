<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_delete_own_thread_without_replies(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $thread = QaThread::factory()
            ->forUser($student)
            ->create();

        // Act
        $response = $this->actingAs($student)
            ->delete(route('qa-board.destroy', $thread));

        // Assert
        $response->assertRedirect(route('qa-board.index'));

        $this->assertDatabaseMissing('qa_threads', [
            'id' => $thread->id,
        ]);
    }

    public function test_student_cannot_delete_thread_with_replies(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $thread = QaThread::factory()
            ->forUser($student)
            ->create();

        QaReply::factory()
            ->forThread($thread)
            ->create();

        // Act
        $response = $this->actingAs($student)
            ->deleteJson(route('qa-board.destroy', $thread));

        // Assert
        $response->assertStatus(409);

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
        ]);
    }

    public function test_admin_can_delete_thread_with_replies(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $thread = QaThread::factory()
            ->forUser($student)
            ->create();

        $reply = QaReply::factory()
            ->forThread($thread)
            ->create();

        // Act
        $response = $this->actingAs($admin)
            ->delete(route('admin.qa-board.destroy', $thread));

        // Assert
        $response->assertRedirect(route('admin.qa-board.index'));

        $this->assertDatabaseMissing('qa_threads', [
            'id' => $thread->id,
        ]);

        $this->assertDatabaseMissing('qa_replies', [
            'id' => $reply->id,
        ]);
    }

    public function test_other_student_cannot_delete_thread(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $thread = QaThread::factory()
            ->forUser($student)
            ->create();

        // Act
        $response = $this->actingAs($otherStudent)
            ->delete(route('qa-board.destroy', $thread));

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
        ]);
    }

    public function test_coach_cannot_delete_thread(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $thread = QaThread::factory()
            ->forUser($student)
            ->create();

        // Act
        $response = $this->actingAs($coach)
            ->delete(route('qa-board.destroy', $thread));

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
        ]);
    }
}
