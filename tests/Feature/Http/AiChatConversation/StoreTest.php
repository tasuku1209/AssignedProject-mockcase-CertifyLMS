<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChatConversation;

use App\Models\AiChatConversation;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use App\UseCases\AiChatMessage\StoreAction as StoreMessageAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_conversation_for_learning_section(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();
        $part = Part::factory()
            ->forCertification($certification)
            ->create();
        $chapter = Chapter::factory()
            ->forPart($part)
            ->create();
        $section = Section::factory()
            ->forChapter($chapter)
            ->create();

        $response = $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.store'), [
                'source' => 'widget',
                'section_id' => $section->id,
            ]);

        $response->assertCreated();
        $conversation = AiChatConversation::query()->latest('created_at')->first();

        $this->assertNotNull($conversation);
        $this->assertSame($student->id, $conversation->user_id);
        $this->assertSame($enrollment->id, $conversation->enrollment_id);
        $this->assertSame($section->id, $conversation->section_id);
        $this->assertTrue($conversation->auto_title_enabled);
        $this->assertNull($conversation->title);

        $response->assertJsonPath(
            'conversation.id',
            $conversation->id,
        );
    }

    public function test_existing_conversation_is_reused_for_same_section(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();
        $part = Part::factory()
            ->forCertification($certification)
            ->create();
        $chapter = Chapter::factory()
            ->forPart($part)
            ->create();
        $section = Section::factory()
            ->forChapter($chapter)
            ->create();

        $conversation = AiChatConversation::factory()
            ->forUser($student)
            ->create([
                'enrollment_id' => $enrollment->id,
                'section_id' => $section->id,
            ]);

        $response = $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.store'), [
                'source' => 'widget',
                'section_id' => $section->id,
            ]);

        $response->assertOk();
        $response->assertJsonPath(
            'conversation.id',
            $conversation->id,
        );

        $this->assertSame(
            1,
            AiChatConversation::query()
                ->where('user_id', $student->id)
                ->where('enrollment_id', $enrollment->id)
                ->where('section_id', $section->id)
                ->count(),
        );
    }

    public function test_student_can_create_conversation_without_section(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();

        $student->update([
            'default_enrollment_id' => $enrollment->id,
        ]);

        $response = $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.store'), [
                'source' => 'full-screen',
            ]);

        $response->assertCreated();

        $conversation = AiChatConversation::query()->latest('created_at')->first();

        $this->assertNotNull($conversation);
        $this->assertSame($student->id, $conversation->user_id);
        $this->assertSame($enrollment->id, $conversation->enrollment_id);
        $this->assertNull($conversation->section_id);
    }

    public function test_conversation_is_created_without_enrollment_when_default_enrollment_is_not_set(): void
    {
        $student = User::factory()->student()->create([
            'default_enrollment_id' => null,
        ]);

        $certification = Certification::factory()->create();

        Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();

        $response = $this->actingAs($student)->postJson(
            route('ai-chat.conversations.store'),
            [
                'source' => 'widget',
            ],
        );

        $response->assertStatus(201);

        $this->assertDatabaseHas('ai_chat_conversations', [
            'user_id' => $student->id,
            'enrollment_id' => null,
            'section_id' => null,
        ]);
    }

    public function test_initial_message_is_delegated_to_message_store_action(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();

        $student->update([
            'default_enrollment_id' => $enrollment->id,
        ]);

        $mock = Mockery::mock(StoreMessageAction::class);

        $mock->shouldReceive('__invoke')
            ->once()
            ->withArgs(function (
                AiChatConversation $conversation,
                User $user,
                array $validated,
            ) use ($student, $enrollment): bool {
                return $conversation->user_id === $student->id
                    && $conversation->enrollment_id === $enrollment->id
                    && $conversation->section_id === null
                    && $user->id === $student->id
                    && $validated === [
                        'content' => 'Laravelについて教えてください。',
                    ];
            })
            ->andReturn([]);

        $this->app->instance(StoreMessageAction::class, $mock);

        $response = $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.store'), [
                'source' => 'full-screen',
                'message' => 'Laravelについて教えてください。',
            ]);

        $response->assertCreated();
    }

    public function test_non_json_request_redirects_to_conversation(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();

        $student->update([
            'default_enrollment_id' => $enrollment->id,
        ]);

        $response = $this->actingAs($student)
            ->post(route('ai-chat.conversations.store'), [
                'source' => 'full-screen',
            ]);

        $response->assertRedirect();

        $conversation = AiChatConversation::query()->latest('created_at')->first();

        $this->assertNotNull($conversation);

        $response->assertRedirect(
            route('ai-chat.conversations.show', $conversation),
        );
    }

    public function test_section_for_certification_not_enrolled_in_cannot_be_used(): void
    {
        $student = User::factory()->student()->create();

        $enrolledCertification = Certification::factory()->create();
        Enrollment::factory()
            ->for($student)
            ->for($enrolledCertification)
            ->learning()
            ->create();

        $otherCertification = Certification::factory()->create();
        $part = Part::factory()
            ->forCertification($otherCertification)
            ->create();
        $chapter = Chapter::factory()
            ->forPart($part)
            ->create();
        $section = Section::factory()
            ->forChapter($chapter)
            ->create();

        $response = $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.store'), [
                'source' => 'widget',
                'section_id' => $section->id,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('section_id');

        $this->assertDatabaseMissing('ai_chat_conversations', [
            'user_id' => $student->id,
            'section_id' => $section->id,
        ]);
    }

    public function test_validation_error_when_source_is_missing(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->postJson(
            route('ai-chat.conversations.store'),
            [],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('source');
    }

    public function test_non_student_cannot_create_conversation(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)
            ->postJson(route('ai-chat.conversations.store'), [
                'source' => 'widget',
            ])
            ->assertForbidden();
    }

    public function test_conversation_is_created_with_default_title_when_auto_title_setting_is_disabled(): void
    {
        // Arrange
        config([
            'ai-chat.auto_title.enabled' => false,
        ]);

        $student = User::factory()->student()->create();

        // Act
        $response = $this->actingAs($student)->postJson(
            route('ai-chat.conversations.store'),
            [
                'source' => 'widget',
            ],
        );

        // Assert
        $response->assertStatus(201);

        $this->assertDatabaseHas('ai_chat_conversations', [
            'user_id' => $student->id,
            'section_id' => null,
            'title' => '新規相談',
            'auto_title_enabled' => false,
        ]);
    }
}
