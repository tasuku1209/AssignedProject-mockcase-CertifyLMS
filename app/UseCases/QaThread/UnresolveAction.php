<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Exceptions\QaThread\QaThreadNotResolvedException;
use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッドを未解決に戻すユースケース。
 *
 * 解決済みのスレッドのみ未解決に変更できる。
 * 未解決の場合は状態遷移を拒否する。
 */
final class UnresolveAction
{
    /**
     * @throws QaThreadNotResolvedException
     */
    public function __invoke(QaThread $thread): QaThread
    {
        if ($thread->status !== QaThreadStatus::Resolved) {
            throw new QaThreadNotResolvedException;
        }

        return DB::transaction(function () use ($thread) {
            $thread->update([
                'status' => QaThreadStatus::Unresolved->value,
                'resolved_at' => null,
            ]);

            return $thread->fresh();
        });
    }
}
