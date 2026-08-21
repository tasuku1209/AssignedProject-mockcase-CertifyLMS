<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaReply;

use App\Models\QaReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_update_own_reply(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $reply = QaReply::factory()
            ->forUser($student)
            ->create([
                'body' => '更新前の回答',
            ]);

        // Act
        $response = $this->actingAs($student)
            ->patch(
                route('qa-board.replies.update', [
                    'thread' => $reply->qa_thread_id,
                    'reply' => $reply->id,
                ]),
                [
                    'body' => '更新後の回答',
                ]
            );

        // Assert
        $response->assertRedirect(
            route('qa-board.show', $reply->qa_thread_id)
        );

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'body' => '更新後の回答',
            'user_id' => $student->id,
        ]);
    }

    public function test_coach_can_update_own_reply(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        $reply = QaReply::factory()
            ->forUser($coach)
            ->create([
                'body' => '更新前の回答',
            ]);

        // Act
        $response = $this->actingAs($coach)
            ->patch(
                route('qa-board.replies.update', [
                    'thread' => $reply->qa_thread_id,
                    'reply' => $reply->id,
                ]),
                [
                    'body' => 'コーチによる更新後の回答',
                ]
            );

        // Assert
        $response->assertRedirect(
            route('qa-board.show', $reply->qa_thread_id)
        );

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'body' => 'コーチによる更新後の回答',
            'user_id' => $coach->id,
        ]);
    }

    public function test_other_student_cannot_update_reply(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $reply = QaReply::factory()
            ->forUser($student)
            ->create([
                'body' => '更新前の回答',
            ]);

        // Act
        $response = $this->actingAs($otherStudent)
            ->patch(
                route('qa-board.replies.update', [
                    'thread' => $reply->qa_thread_id,
                    'reply' => $reply->id,
                ]),
                [
                    'body' => '不正な更新',
                ]
            );

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'body' => '更新前の回答',
        ]);
    }

    public function test_admin_cannot_update_reply(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $reply = QaReply::factory()
            ->forUser($student)
            ->create([
                'body' => '更新前の回答',
            ]);

        // Act
        $response = $this->actingAs($admin)
            ->patch(
                route('qa-board.replies.update', [
                    'thread' => $reply->qa_thread_id,
                    'reply' => $reply->id,
                ]),
                [
                    'body' => '管理者による不正な更新',
                ]
            );

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'body' => '更新前の回答',
        ]);
    }

    public function test_body_is_required(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $reply = QaReply::factory()
            ->forUser($student)
            ->create([
                'body' => '更新前の回答',
            ]);

        // Act
        $response = $this->actingAs($student)
            ->patch(
                route('qa-board.replies.update', [
                    'thread' => $reply->qa_thread_id,
                    'reply' => $reply->id,
                ]),
                [
                    'body' => '',
                ]
            );

        // Assert
        $response->assertSessionHasErrors('body');

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'body' => '更新前の回答',
        ]);
    }

    public function test_body_cannot_exceed_5000_characters(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $reply = QaReply::factory()
            ->forUser($student)
            ->create([
                'body' => '更新前の回答',
            ]);

        // Act
        $response = $this->actingAs($student)
            ->patch(
                route('qa-board.replies.update', [
                    'thread' => $reply->qa_thread_id,
                    'reply' => $reply->id,
                ]),
                [
                    'body' => str_repeat('a', 5001),
                ]
            );

        // Assert
        $response->assertSessionHasErrors('body');

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'body' => '更新前の回答',
        ]);
    }
}
