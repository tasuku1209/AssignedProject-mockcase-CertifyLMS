<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Models\Plan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * admin 用のプランマスタ一覧をフィルタ付きで取得するユースケース。
 *
 * フィルタ: keyword(プラン名の部分一致) / status
 * 各プランに紐づく受講者数を取得する。
 */
final class IndexAction
{
    public function __invoke(
        ?string $keyword = null,
        ?string $status = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = Plan::query()
            ->withCount('users');

        if ($keyword !== null && $keyword !== '') {
            $query->where('name', 'LIKE', '%'.$keyword.'%');
        }

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        return $query
            ->ordered()
            ->paginate($perPage)
            ->withQueryString();
    }
}
