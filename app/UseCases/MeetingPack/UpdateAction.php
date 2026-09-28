<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 面談パックを更新するユースケース。
 *
 * status は状態遷移処理と分離し、
 * name / description / meeting_count / price / stripe_price_id / sort_order のみ更新する。
 */
final class UpdateAction
{
    /**
     * @param array{
     *     name: string,
     *     description?: ?string,
     *     meeting_count: int,
     *     price: int,
     *     stripe_price_id?: ?string,
     *     sort_order?: int
     * } $validated
     */
    public function __invoke(
        MeetingPack $meetingPack,
        User $auth,
        array $validated
    ): MeetingPack {
        return DB::transaction(function () use ($meetingPack, $auth, $validated) {
            $meetingPack->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'meeting_count' => $validated['meeting_count'],
                'price' => $validated['price'],
                'stripe_price_id' => $validated['stripe_price_id'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
                'updated_by_user_id' => $auth->id,
            ]);

            return $meetingPack->fresh();
        });
    }
}
