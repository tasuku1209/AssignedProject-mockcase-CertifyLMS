<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;

/**
 * 質問掲示板の質問詳細を取得するユースケース。
 * 質問者・資格・回答・回答者を Eager Load し、回答数を取得する。
 */
final class ShowAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        return $thread
            ->load([
                'user',
                'certification',
                'replies.user',
            ])
            ->loadCount('replies');
    }
}
