<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Exceptions\QaThread\QaThreadAlreadyResolvedException;
use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッドを解決済みにするユースケース。
 *
 * 未解決のスレッドのみ解決済みに変更できる。
 * すでに解決済みの場合は状態遷移を拒否する。
 */
final class ResolveAction
{
    /**
     * @throws QaThreadAlreadyResolvedException
     */
    public function __invoke(QaThread $thread): QaThread
    {
        if ($thread->status === QaThreadStatus::Resolved) {
            throw new QaThreadAlreadyResolvedException;
        }

        return DB::transaction(function () use ($thread) {
            $thread->update([
                'status' => QaThreadStatus::Resolved->value,
                'resolved_at' => now(),
            ]);

            return $thread->fresh();
        });
    }
}
