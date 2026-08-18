<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 質問掲示板のスレッドを新規作成するユースケース。
 * 投稿者・資格・タイトル・本文を登録し、初期状態を未解決とする。
 */
final class StoreAction
{
    /**
     * @param array{
     *     certification_id: string,
     *     title: string,
     *     body: string
     * } $validated
     */
    public function __invoke(User $user, array $validated): QaThread
    {
        return DB::transaction(fn () => QaThread::create([
            'user_id' => $user->id,
            'certification_id' => $validated['certification_id'],
            'title' => $validated['title'],
            'body' => $validated['body'],
            'status' => QaThreadStatus::Unresolved->value,
        ]));
    }
}
