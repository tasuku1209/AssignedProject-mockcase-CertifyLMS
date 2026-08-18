<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 質問掲示板のスレッドを更新するユースケース。
 * 資格は変更せず、タイトルと本文のみを更新する。
 */
final class UpdateAction
{
    /**
     * @param array{
     *     title: string,
     *     body: string
     * } $validated
     */
    public function __invoke(QaThread $thread, array $validated): QaThread
    {
        return DB::transaction(function () use ($thread, $validated) {
            $thread->update([
                'title' => $validated['title'],
                'body' => $validated['body'],
            ]);

            return $thread->fresh();
        });
    }
}
