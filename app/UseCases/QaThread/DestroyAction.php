<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\UserRole;
use App\Exceptions\QaThread\QaThreadNotDeletableException;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 質問掲示板のスレッドを削除するユースケース。
 *
 * 回答が付いているスレッドは削除不可とする。
 * 管理者によるモデレーション削除では、回答も含めて削除する。
 */
final class DestroyAction
{
    /**
     * @throws QaThreadNotDeletableException 回答が付いているスレッドは削除不可
     */
    public function __invoke(QaThread $thread, User $user): void
    {
        if (
            $user->role !== UserRole::Admin
            && $thread->replies()->exists()
        ) {
            throw new QaThreadNotDeletableException;
        }

        DB::transaction(fn () => $thread->delete());
    }
}
