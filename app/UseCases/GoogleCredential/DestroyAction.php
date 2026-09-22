<?php

declare(strict_types=1);

namespace App\UseCases\GoogleCredential;

use App\Models\GoogleCredential;
use Illuminate\Support\Facades\DB;

/**
 * Google Calendar の連携情報を削除するユースケース。
 *
 * Google側のカレンダーイベントは削除せず、
 * LMS側のGoogle Calendar連携情報のみ削除する。
 * 本人所有確認は Controller / Policy で完了済みの前提。
 */
final class DestroyAction
{
    public function __invoke(GoogleCredential $credential): void
    {
        DB::transaction(fn () => $credential->delete());
    }
}
