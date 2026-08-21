<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaReply;

use App\Models\QaReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_delete_own_reply(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $reply = QaReply::factory()
            ->forUser($student)
            ->create();

        // Act
        $response = $this->actingAs($student)
            ->delete(route('qa-board.replies.destroy', [
                'thread' => $reply->qa_thread_id,
                'reply' => $reply->id,
            ]));

        // Assert
        $response->assertRedirect(
            route('qa-board.show', $reply->qa_thread_id)
        );

        $this->assertDatabaseMissing('qa_replies', [
            'id' => $reply->id,
        ]);
    }

    public function test_coach_can_delete_own_reply(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        $reply = QaReply::factory()
            ->forUser($coach)
            ->create();

        // Act
        $response = $this->actingAs($coach)
            ->delete(route('qa-board.replies.destroy', [
                'thread' => $reply->qa_thread_id,
                'reply' => $reply->id,
            ]));

        // Assert
        $response->assertRedirect(
            route('qa-board.show', $reply->qa_thread_id)
        );

        $this->assertDatabaseMissing('qa_replies', [
            'id' => $reply->id,
        ]);
    }

    public function test_admin_can_delete_any_reply(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $reply = QaReply::factory()
            ->forUser($student)
            ->create();

        // Act
        $response = $this->actingAs($admin)
            ->delete(route('admin.qa-board.replies.destroy', [
                'thread' => $reply->qa_thread_id,
                'reply' => $reply->id,
            ]));

        // Assert
        $response->assertRedirect(
            route('admin.qa-board.show', $reply->qa_thread_id)
        );

        $this->assertDatabaseMissing('qa_replies', [
            'id' => $reply->id,
        ]);
    }

    public function test_other_student_cannot_delete_reply(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $reply = QaReply::factory()
            ->forUser($student)
            ->create();

        // Act
        $response = $this->actingAs($otherStudent)
            ->delete(route('qa-board.replies.destroy', [
                'thread' => $reply->qa_thread_id,
                'reply' => $reply->id,
            ]));

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
        ]);
    }

    public function test_other_coach_cannot_delete_reply(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();

        $reply = QaReply::factory()
            ->forUser($coach)
            ->create();

        // Act
        $response = $this->actingAs($otherCoach)
            ->delete(route('qa-board.replies.destroy', [
                'thread' => $reply->qa_thread_id,
                'reply' => $reply->id,
            ]));

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
        ]);
    }
}
