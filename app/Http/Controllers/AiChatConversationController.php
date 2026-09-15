<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AiChatConversation\StoreRequest;
use App\Http\Requests\AiChatConversation\UpdateRequest;
use App\Models\AiChatConversation;
use App\UseCases\AiChatConversation\DestroyAction;
use App\UseCases\AiChatConversation\IndexAction;
use App\UseCases\AiChatConversation\ShowAction;
use App\UseCases\AiChatConversation\StoreAction;
use App\UseCases\AiChatConversation\UpdateAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 受講生用のAIチャット相談 Conversation 管理画面 Controller。
 *
 * Conversation の一覧表示・作成・表示・タイトル更新・削除を提供する。
 */
class AiChatConversationController extends Controller
{
    public function index(IndexAction $action): RedirectResponse|View
    {
        $this->authorize('viewAny', AiChatConversation::class);

        $conversation = $action(request()->user());

        if ($conversation === null) {
            return view('ai-chat.empty-state');
        }

        return redirect()
            ->route('ai-chat.conversations.show', $conversation);
    }

    public function store(
        StoreRequest $request,
        StoreAction $action,
    ): JsonResponse|RedirectResponse {
        $conversation = $action(
            $request->user(),
            $request->validated(),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'conversation' => $conversation,
            ], 201);
        }

        return redirect()
            ->route('ai-chat.conversations.show', $conversation);
    }

    public function show(AiChatConversation $conversation, ShowAction $action): View
    {
        $this->authorize('view', $conversation);

        return view('ai-chat.show', [
            'conversation' => $action($conversation),
        ]);
    }

    public function update(
        AiChatConversation $conversation,
        UpdateRequest $request,
        UpdateAction $action,
    ): RedirectResponse {
        $action(
            $conversation,
            $request->validated(),
        );

        return redirect()
            ->route('ai-chat.conversations.show', $conversation)
            ->with('success', 'AI相談のタイトルを更新しました。');
    }

    public function destroy(
        AiChatConversation $conversation,
        DestroyAction $action,
    ): RedirectResponse {
        $this->authorize('delete', $conversation);

        $action($conversation);

        return redirect()
            ->route('ai-chat.index')
            ->with('success', 'AI相談を削除しました。');
    }
}
