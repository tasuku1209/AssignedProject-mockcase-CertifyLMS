<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanInvalidTransitionException;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 受講プランを公開するユースケース。
 *
 * 下書き状態のプランのみ公開可能。
 */
final class PublishAction
{
    /**
     * @throws PlanInvalidTransitionException
     */
    public function __invoke(Plan $plan, User $auth): Plan
    {
        if ($plan->status !== PlanStatus::Draft) {
            throw PlanInvalidTransitionException::forPublish();
        }

        return DB::transaction(function () use ($plan, $auth) {
            $plan->update([
                'status' => PlanStatus::Published,
                'updated_by_user_id' => $auth->id,
            ]);

            return $plan->fresh();
        });
    }
}
