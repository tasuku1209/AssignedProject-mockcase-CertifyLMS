<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_thread_with_published_certification(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()
            ->for($student, 'user')
            ->for($certification, 'certification')
            ->create();

        // Act
        $response = $this->actingAs($student)
            ->get(route('qa-board.show', $thread));

        // Assert
        $response->assertOk();
        $response->assertViewIs('qa-thread.show');
        $response->assertViewHas('thread');
    }

    public function test_student_gets_403_on_thread_with_draft_certification(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->draft()->create();

        $thread = QaThread::factory()
            ->for($student, 'user')
            ->for($certification, 'certification')
            ->create();

        // Act
        $response = $this->actingAs($student)
            ->get(route('qa-board.show', $thread));

        // Assert
        $response->assertForbidden();
    }

    public function test_student_gets_403_on_thread_with_archived_certification(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->archived()->create();

        $thread = QaThread::factory()
            ->for($student, 'user')
            ->for($certification, 'certification')
            ->create();

        // Act
        $response = $this->actingAs($student)
            ->get(route('qa-board.show', $thread));

        // Assert
        $response->assertForbidden();
    }

    public function test_assigned_coach_can_view_thread(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $thread = QaThread::factory()
            ->for($student, 'user')
            ->for($certification, 'certification')
            ->create();

        // Act
        $response = $this->actingAs($coach)
            ->get(route('qa-board.show', $thread));

        // Assert
        $response->assertOk();
        $response->assertViewIs('qa-thread.show');
    }

    public function test_unassigned_coach_gets_403_on_thread(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()
            ->for($student, 'user')
            ->for($certification, 'certification')
            ->create();

        // Act
        $response = $this->actingAs($coach)
            ->get(route('qa-board.show', $thread));

        // Assert
        $response->assertForbidden();
    }

    public function test_admin_can_view_any_thread(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->draft()->create();

        $thread = QaThread::factory()
            ->for($student, 'user')
            ->for($certification, 'certification')
            ->create();

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.qa-board.show', $thread));

        // Assert
        $response->assertOk();
        $response->assertViewIs('qa-thread.show');
    }

    public function test_show_loads_replies_and_reply_count(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()
            ->for($student, 'user')
            ->for($certification, 'certification')
            ->create();

        QaReply::factory()
            ->for($thread, 'qaThread')
            ->count(3)
            ->create();

        // Act
        $response = $this->actingAs($student)
            ->get(route('qa-board.show', $thread));

        // Assert
        $response->assertOk();

        $loadedThread = $response->viewData('thread');

        $this->assertCount(3, $loadedThread->replies);
        $this->assertSame(3, $loadedThread->replies_count);
    }
}
