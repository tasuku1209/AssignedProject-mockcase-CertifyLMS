<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Models\Announcement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexAction
{
    /**
     * 配信済みお知らせの一覧を取得する。
     *
     * @return LengthAwarePaginator<int, Announcement>
     */
    public function __invoke(): LengthAwarePaginator
    {
        return Announcement::query()
            ->with([
                'targetCertification',
                'targetUser',
                'createdBy',
            ])
            ->orderByDesc('dispatched_at')
            ->paginate(20);
    }
}
