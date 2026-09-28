<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * admin 用の面談パック一覧をフィルタ付きで取得するユースケース。
 *
 * フィルタ: keyword(部分一致) / status
 */
final class IndexAction
{
    public function __invoke(
        ?string $keyword = null,
        ?string $status = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = MeetingPack::query();

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
