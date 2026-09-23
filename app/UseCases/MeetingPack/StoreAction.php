<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 面談パックを新規作成するユースケース。
 *
 * 新規作成時は必ず下書き(draft)として登録し、
 * 作成者・最終更新者に操作ユーザーを記録する。
 */
final class StoreAction
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
    public function __invoke(User $auth, array $validated): MeetingPack
    {
        return DB::transaction(fn () => MeetingPack::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'meeting_count' => $validated['meeting_count'],
            'price' => $validated['price'],
            'stripe_price_id' => $validated['stripe_price_id'] ?? null,
            'status' => MeetingPackStatus::Draft->value,
            'sort_order' => $validated['sort_order'] ?? 0,
            'created_by_user_id' => $auth->id,
            'updated_by_user_id' => $auth->id,
        ]));
    }
}
