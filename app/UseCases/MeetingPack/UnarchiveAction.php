<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingPack\MeetingPackInvalidTransitionException;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 面談パックをアーカイブから下書きへ戻すユースケース。
 *
 * 許容遷移: archived → draft
 * 下書きまたは公開中の面談パックは下書きへ戻せない。
 */
final class UnarchiveAction
{
    /**
     * @throws MeetingPackInvalidTransitionException
     */
    public function __invoke(MeetingPack $plan, User $auth): MeetingPack
    {
        if ($plan->status !== MeetingPackStatus::Archived) {
            throw MeetingPackInvalidTransitionException::forUnarchive();
        }

        return DB::transaction(function () use ($plan, $auth) {
            $plan->update([
                'status' => MeetingPackStatus::Draft,
                'updated_by_user_id' => $auth->id,
            ]);

            return $plan->fresh();
        });
    }
}
