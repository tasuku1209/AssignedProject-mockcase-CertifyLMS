<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 質問掲示板の一覧をフィルタ付きで取得するユースケース。
 *
 * 受講生は公開済み資格、コーチは担当資格を対象として、
 * 解決状態・資格・キーワードで絞り込み、
 * 投稿者・資格を Eager Load、回答数を取得してページネーションする。
 */
final class IndexAction
{
    public function __invoke(
        User $user,
        ?string $keyword,
        ?QaThreadStatus $status,
        ?string $certificationId,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = QaThread::query()
            ->with(['user', 'certification'])
            ->withCount('replies');

        if ($user->role === UserRole::Student) {
            $query->whereHas(
                'certification',
                fn ($query) => $query->published()
            );
        }

        if ($user->role === UserRole::Coach) {
            $query->whereHas('certification', function ($query) use ($user) {
                $query
                    ->published()
                    ->whereHas('coaches', fn ($query) => $query->where('users.id', $user->id));
            });
        }

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
