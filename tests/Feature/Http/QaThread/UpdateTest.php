<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_update_own_thread(): void
    {
        $student = User::factory()->student()->create();

        $thread = QaThread::factory()
            ->forUser($student)
            ->create([
                'title' => '更新前のタイトル',
                'body' => '更新前の本文',
            ]);

        $payload = [
            'title' => '更新後のタイトル',
            'body' => '更新後の本文',
        ];

        $response = $this->actingAs($student)
            ->patch(route('qa-board.update', $thread), $payload);

        $response->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'title' => '更新後のタイトル',
            'body' => '更新後の本文',
        ]);
    }

    public function test_other_student_cannot_update_thread(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $thread = QaThread::factory()
            ->forUser($student)
            ->create([
                'title' => '更新前のタイトル',
                'body' => '更新前の本文',
            ]);

        $response = $this->actingAs($otherStudent)
            ->patch(route('qa-board.update', $thread), [
                'title' => '不正な更新',
                'body' => '不正な本文',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'title' => '更新前のタイトル',
            'body' => '更新前の本文',
        ]);
    }

    public function test_coach_cannot_update_thread(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $thread = QaThread::factory()
            ->forUser($student)
            ->create();

        $response = $this->actingAs($coach)
            ->patch(route('qa-board.update', $thread), [
                'title' => '不正な更新',
                'body' => '不正な本文',
            ]);

        $response->assertForbidden();
    }

    public function test_required_fields_are_validated(): void
    {
        $student = User::factory()->student()->create();

        $thread = QaThread::factory()
            ->forUser($student)
            ->create();

        $this->actingAs($student)
            ->patch(route('qa-board.update', $thread), [
                'title' => '',
                'body' => '本文',
            ])
            ->assertSessionHasErrors('title');

        $this->actingAs($student)
            ->patch(route('qa-board.update', $thread), [
                'title' => 'タイトル',
                'body' => '',
            ])
            ->assertSessionHasErrors('body');
    }
}
