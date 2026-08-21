<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 管理者モデレーション用の質問掲示板一覧をフィルタ付きで取得するユースケース。
 *
 * 解決状態・資格・キーワードで絞り込み、
 * 投稿者・資格を Eager Load、回答数を取得してページネーションする。
 *
 * 管理者は公開停止中の資格を含む全資格のスレッドを閲覧できるため、
 * 公開資格への限定は行わない。
 */
final class IndexAsAdminAction
{
    public function __invoke(
        ?string $keyword,
        ?QaThreadStatus $status,
        ?string $certificationId,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = QaThread::query()
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
