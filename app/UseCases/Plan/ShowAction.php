<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Models\Plan;

/**
 * プランマスタ詳細を取得するユースケース。
 *
 * 受講者一覧と作成者・最終更新者を併せて取得する。
 */
final class ShowAction
{
    public function __invoke(Plan $plan): Plan
    {
        return $plan->load([
            'users',
            'createdBy',
            'updatedBy',
        ]);
    }
}
