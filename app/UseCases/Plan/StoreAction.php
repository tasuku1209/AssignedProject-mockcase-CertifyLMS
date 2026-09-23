<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 受講プランを新規作成するユースケース。
 *
 * 新規作成時は必ず下書き(Draft)として登録し、
 * 作成者・更新者を記録する。
 */
final class StoreAction
{
    /**
     * @param array{
     *     name: string,
     *     description?: ?string,
     *     duration_days: int,
     *     default_meeting_quota: int,
     *     sort_order?: int
     * } $validated
     */
    public function __invoke(User $auth, array $validated): Plan
    {
        return DB::transaction(fn () => Plan::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'duration_days' => $validated['duration_days'],
            'default_meeting_quota' => $validated['default_meeting_quota'],
            'status' => PlanStatus::Draft,
            'sort_order' => $validated['sort_order'] ?? 0,
            'created_by_user_id' => $auth->id,
            'updated_by_user_id' => $auth->id,
        ]));
    }
}
