<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingPack\MeetingPackInvalidTransitionException;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 面談パックを公開するユースケース。
 *
 * 許容遷移: draft → published
 * 公開中またはアーカイブ済みの面談パックは公開できない。
 */
final class PublishAction
{
    /**
     * @throws MeetingPackInvalidTransitionException
     */
    public function __invoke(MeetingPack $plan, User $auth): MeetingPack
    {
        if ($plan->status !== MeetingPackStatus::Draft) {
            throw MeetingPackInvalidTransitionException::forPublish();
        }

        return DB::transaction(function () use ($plan, $auth) {
            $plan->update([
                'status' => MeetingPackStatus::Published,
                'updated_by_user_id' => $auth->id,
            ]);

            return $plan->fresh();
        });
    }
}
