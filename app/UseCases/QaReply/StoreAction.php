<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaReplyReceivedNotification;
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
        return DB::transaction(function () use ($user, $thread, $validated) {
            $reply = QaReply::create([
                'qa_thread_id' => $thread->id,
                'user_id' => $user->id,
                'body' => $validated['body'],
            ]);

            $threadOwner = $thread->user;

            // スレッド投稿者本人が自分のスレッドに回答した場合は通知しない。
            // 通知対象者が受講生かつ受講中の場合のみ通知する。
            if (
                $thread->user_id !== $user->id
                && $threadOwner->role === UserRole::Student
                && $threadOwner->status === UserStatus::InProgress
            ) {
                $threadOwner->notify(
                    new QaReplyReceivedNotification($reply)
                );
            }

            return $reply;
        });
    }
}
