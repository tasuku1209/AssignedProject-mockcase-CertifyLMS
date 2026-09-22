<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * EnsureAiChatEnabled Middleware の検証。
 *
 * AIチャット機能が有効な場合は後続処理へ通過し、
 * 無効な場合は 403 Forbidden となることを確認する。
 */
class EnsureAiChatEnabledTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__test/ai-chat-enabled', fn () => 'ai-chat-page')
            ->middleware(['auth', 'role:student', 'active-learning', 'ai-chat-enabled'])
            ->name('__test.ai-chat.enabled');

        app('router')->getRoutes()->refreshNameLookups();
    }

    public function test_passes_through_when_ai_chat_is_enabled(): void
    {
        config([
            'ai-chat.enabled' => true,
        ]);

        $user = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $response = $this->actingAs($user)->get('/__test/ai-chat-enabled');

        $response->assertOk();
        $response->assertSee('ai-chat-page');
    }

    public function test_returns_forbidden_when_ai_chat_is_disabled(): void
    {
        config([
            'ai-chat.enabled' => false,
        ]);

        $user = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $response = $this->actingAs($user)->get('/__test/ai-chat-enabled');

        $response->assertForbidden();
    }
}
