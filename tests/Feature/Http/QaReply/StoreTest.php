<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaReply;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_reply_on_published_certification_thread(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()
            ->for($certification, 'certification')
            ->create();

        // Act
        $response = $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => '回答本文です。',
            ]);

        // Assert
        $response->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
            'body' => '回答本文です。',
        ]);
    }

    public function test_assigned_coach_can_create_reply(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();

        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $thread = QaThread::factory()
            ->for($certification, 'certification')
            ->create();

        // Act
        $response = $this->actingAs($coach)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => 'コーチからの回答です。',
            ]);

        // Assert
        $response->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $coach->id,
            'body' => 'コーチからの回答です。',
        ]);
    }

    public function test_admin_cannot_create_reply(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()
            ->for($certification, 'certification')
            ->create();

        // Act
        $response = $this->actingAs($admin)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => '管理者からの回答です。',
            ]);

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_student_cannot_create_reply_on_unpublished_certification_thread(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->draft()->create();

        $thread = QaThread::factory()
            ->for($certification, 'certification')
            ->create();

        // Act
        $response = $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => '回答本文です。',
            ]);

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_unassigned_coach_cannot_create_reply(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()
            ->for($certification, 'certification')
            ->create();

        // Act
        $response = $this->actingAs($coach)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => '回答本文です。',
            ]);

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $coach->id,
        ]);
    }

    public function test_body_is_required(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()
            ->for($certification, 'certification')
            ->create();

        // Act
        $response = $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => '',
            ]);

        // Assert
        $response->assertSessionHasErrors('body');
    }

    public function test_body_cannot_exceed_5000_characters(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()
            ->for($certification, 'certification')
            ->create();

        // Act
        $response = $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => str_repeat('a', 5001),
            ]);

        // Assert
        $response->assertSessionHasErrors('body');
    }
}
