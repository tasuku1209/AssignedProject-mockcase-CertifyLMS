<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function payload(Certification $certification, array $override = []): array
    {
        return array_merge([
            'certification_id' => $certification->id,
            'title' => '新しい質問です',
            'body' => 'これは質問本文です。',
        ], $override);
    }

    public function test_student_can_create_thread(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        // Act
        $response = $this->actingAs($student)
            ->post(
                route('qa-board.store'),
                $this->payload($certification)
            );

        // Assert
        $response->assertRedirect();

        $this->assertDatabaseHas('qa_threads', [
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => '新しい質問です',
            'body' => 'これは質問本文です。',
            'status' => QaThreadStatus::Unresolved->value,
        ]);
    }

    public function test_required_fields_are_validated(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        // Act & Assert
        $this->actingAs($student)
            ->post(
                route('qa-board.store'),
                $this->payload($certification, ['certification_id' => ''])
            )
            ->assertSessionHasErrors('certification_id');

        $this->actingAs($student)
            ->post(
                route('qa-board.store'),
                $this->payload($certification, ['title' => ''])
            )
            ->assertSessionHasErrors('title');

        $this->actingAs($student)
            ->post(
                route('qa-board.store'),
                $this->payload($certification, ['body' => ''])
            )
            ->assertSessionHasErrors('body');
    }

    public function test_student_cannot_create_thread_for_unpublished_certification(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->draft()->create();

        // Act
        $response = $this->actingAs($student)
            ->post(
                route('qa-board.store'),
                $this->payload($certification)
            );

        // Assert
        $response->assertSessionHasErrors('certification_id');

        $this->assertDatabaseMissing('qa_threads', [
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);
    }

    public function test_coach_cannot_create_thread(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();

        // Act
        $response = $this->actingAs($coach)
            ->post(
                route('qa-board.store'),
                $this->payload($certification)
            );

        // Assert
        $response->assertForbidden();
    }

    public function test_admin_cannot_create_thread(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        // Act
        $response = $this->actingAs($admin)
            ->post(
                route('qa-board.store'),
                $this->payload($certification)
            );

        // Assert
        $response->assertForbidden();
    }
}
