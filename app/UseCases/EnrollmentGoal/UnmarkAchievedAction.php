<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Exceptions\EnrollmentGoal\EnrollmentGoalAlreadyUnachievedException;
use App\Models\EnrollmentGoal;
use Illuminate\Support\Facades\DB;

/**
 * 個人目標の達成を解除するユースケース。
 *
 * - 未達成の場合は `EnrollmentGoalAlreadyUnachievedException` (HTTP 409)
 * - 達成済みの場合のみ achieved_at を NULL に戻す。
 */
final class UnmarkAchievedAction
{
    /**
     * @throws EnrollmentGoalAlreadyUnachievedException
     */
    public function __invoke(EnrollmentGoal $goal): EnrollmentGoal
    {
        if ($goal->achieved_at === null) {
            throw new EnrollmentGoalAlreadyUnachievedException;
        }

        return DB::transaction(function () use ($goal) {
            $goal->update([
                'achieved_at' => null,
            ]);

            return $goal->fresh();
        });
    }
}
