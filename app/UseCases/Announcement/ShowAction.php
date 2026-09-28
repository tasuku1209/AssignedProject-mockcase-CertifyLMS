<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Models\Announcement;

class ShowAction
{
    /**
     * お知らせ詳細表示に必要なリレーションを読み込む。
     */
    public function __invoke(Announcement $announcement): Announcement
    {
        return $announcement->load([
            'targetCertification',
            'targetUser',
            'createdBy',
        ]);
    }
}
