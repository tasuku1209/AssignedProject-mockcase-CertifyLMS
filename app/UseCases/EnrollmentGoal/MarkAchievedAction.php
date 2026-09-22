<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Exceptions\EnrollmentGoal\EnrollmentGoalAlreadyAchievedException;
use App\Models\EnrollmentGoal;
use Illuminate\Support\Facades\DB;

/**
 * 個人目標を達成済みにするユースケース。
 *
 * 既に達成済みの目標は再度達成済みにできない。
 * 不正な状態遷移は HTTP 409 Conflict として扱う。
 */
final class MarkAchievedAction
{
    /**
     * @throws EnrollmentGoalAlreadyAchievedException
     */
    public function __invoke(EnrollmentGoal $goal): EnrollmentGoal
    {
        if ($goal->achieved_at !== null) {
            throw new EnrollmentGoalAlreadyAchievedException;
        }

        return DB::transaction(function () use ($goal) {
            $goal->update([
                'achieved_at' => now(),
            ]);

            return $goal->fresh();
        });
    }
}
