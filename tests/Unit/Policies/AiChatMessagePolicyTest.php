<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\AiChatConversation;
use App\Models\User;
use App\Policies\AiChatMessagePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AiChatMessagePolicy の判定を検証する Unit テスト。
 * create は自分が所有する Conversation に対する student のみ許可。
 */
class AiChatMessagePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_allowed_only_for_owner_student(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $conversation = AiChatConversation::factory()
            ->forUser($student)
            ->create();

        $policy = new AiChatMessagePolicy;

        $this->assertTrue(
            $policy->create($student, $conversation),
            'Conversation の所有者である student はメッセージ送信可',
        );

        $this->assertFalse(
            $policy->create($otherStudent, $conversation),
            '他 student はメッセージ送信不可',
        );

        $this->assertFalse(
            $policy->create($coach, $conversation),
            'coach はメッセージ送信不可',
        );

        $this->assertFalse(
            $policy->create($admin, $conversation),
            'admin はメッセージ送信不可',
        );
    }
}
