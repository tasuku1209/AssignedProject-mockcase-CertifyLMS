<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\AiChatConversation;
use App\Models\User;
use App\Policies\AiChatConversationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AiChatConversationPolicy の判定を検証する Unit テスト。
 * viewAny / create は student のみ / view・update・delete は自分の Conversation のみ。
 */
class AiChatConversationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_any_allowed_only_for_student(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $policy = new AiChatConversationPolicy;

        $this->assertTrue($policy->viewAny($student));
        $this->assertFalse($policy->viewAny($coach));
        $this->assertFalse($policy->viewAny($admin));
    }

    public function test_view_only_for_owner_student(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $conversation = AiChatConversation::factory()
            ->forUser($student)
            ->create();

        $policy = new AiChatConversationPolicy;

        $this->assertTrue($policy->view($student, $conversation));
        $this->assertFalse(
            $policy->view($otherStudent, $conversation),
            '他 student の Conversation は view 不可',
        );
        $this->assertFalse(
            $policy->view($coach, $conversation),
            'coach は view 不可',
        );
        $this->assertFalse(
            $policy->view($admin, $conversation),
            'admin は view 不可',
        );
    }

    public function test_create_allowed_only_for_student(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $policy = new AiChatConversationPolicy;

        $this->assertTrue($policy->create($student));
        $this->assertFalse($policy->create($coach));
        $this->assertFalse($policy->create($admin));
    }

    public function test_update_only_for_owner_student(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $conversation = AiChatConversation::factory()
            ->forUser($student)
            ->create();

        $policy = new AiChatConversationPolicy;

        $this->assertTrue($policy->update($student, $conversation));
        $this->assertFalse(
            $policy->update($otherStudent, $conversation),
            '他 student の Conversation は update 不可',
        );
        $this->assertFalse(
            $policy->update($coach, $conversation),
            'coach は update 不可',
        );
        $this->assertFalse(
            $policy->update($admin, $conversation),
            'admin は update 不可',
        );
    }

    public function test_delete_only_for_owner_student(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $conversation = AiChatConversation::factory()
            ->forUser($student)
            ->create();

        $policy = new AiChatConversationPolicy;

        $this->assertTrue($policy->delete($student, $conversation));
        $this->assertFalse(
            $policy->delete($otherStudent, $conversation),
            '他 student の Conversation は delete 不可',
        );
        $this->assertFalse(
            $policy->delete($coach, $conversation),
            'coach は delete 不可',
        );
        $this->assertFalse(
            $policy->delete($admin, $conversation),
            'admin は delete 不可',
        );
    }
}
