<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;

/**
 * 面談パック詳細を取得するユースケース。
 *
 * 作成者・最終更新者を併せて取得する。
 * 購入履歴は Payment 実装後に追加する。
 */
final class ShowAction
{
    public function __invoke(MeetingPack $meetingPack): MeetingPack
    {
        return $meetingPack
            ->load(['createdBy', 'updatedBy']);
    }
}
