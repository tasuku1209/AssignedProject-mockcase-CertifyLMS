<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッドへの回答を新規登録するユースケース。
 */
final class StoreAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(User $user, QaThread $thread, array $validated): QaReply
    {
        return DB::transaction(fn () => QaReply::create([
            'qa_thread_id' => $thread->id,
            'user_id' => $user->id,
            'body' => $validated['body'],
        ]));
    }
}
