<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingPack\MeetingPackInvalidTransitionException;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 面談パックをアーカイブするユースケース。
 *
 * 許容遷移: published → archived
 * 下書きまたはアーカイブ済みの面談パックはアーカイブできない。
 */
final class ArchiveAction
{
    /**
     * @throws MeetingPackInvalidTransitionException
     */
    public function __invoke(MeetingPack $plan, User $auth): MeetingPack
    {
        if ($plan->status !== MeetingPackStatus::Published) {
            throw MeetingPackInvalidTransitionException::forArchive();
        }

        return DB::transaction(function () use ($plan, $auth) {
            $plan->update([
                'status' => MeetingPackStatus::Archived,
                'updated_by_user_id' => $auth->id,
            ]);

            return $plan->fresh();
        });
    }
}
