<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\QaReply\StoreRequest;
use App\Http\Requests\QaReply\UpdateRequest;
use App\Models\QaReply;
use App\Models\QaThread;
use App\UseCases\QaReply\DestroyAction;
use App\UseCases\QaReply\StoreAction;
use App\UseCases\QaReply\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class QaReplyController extends Controller
{
    public function store(QaThread $thread, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $action(
            user: $request->user(),
            thread: $thread,
            validated: $request->validated(),
        );

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を投稿しました。');
    }

    public function edit(QaReply $reply): View
    {
        $this->authorize('update', $reply);

        return view('qa-thread.reply-edit', [
            'reply' => $reply,
        ]);
    }

    public function update(QaReply $reply, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action(
            reply: $reply,
            validated: $request->validated(),
        );

        return redirect()
            ->route('qa-board.show', $reply->qaThread)
            ->with('success', '回答を更新しました。');
    }

    public function destroy(QaReply $reply, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $reply);

        $thread = $reply->qaThread;

        $action($reply);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を削除しました。');
    }
}
