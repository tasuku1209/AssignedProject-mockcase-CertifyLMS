<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 質問掲示板の一覧をフィルタ付きで取得するユースケース。
 *
 * 解決状態・資格・キーワードで絞り込み、
 * 投稿者・資格を Eager Load、回答数を取得してページネーションする。
 */
final class IndexAction
{
    public function __invoke(
        ?string $keyword,
        ?QaThreadStatus $status,
        ?string $certificationId,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = QaThread::query()
            ->whereHas('certification', fn ($query) => $query->published())
            ->with(['user', 'certification'])
            ->withCount('replies');

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        if ($certificationId !== null && $certificationId !== '') {
            $query->where('certification_id', $certificationId);
        }

        if ($keyword !== null && $keyword !== '') {
            $query->where(function ($query) use ($keyword) {
                $query
                    ->where('title', 'like', "%{$keyword}%")
                    ->orWhere('body', 'like', "%{$keyword}%");
            });
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
