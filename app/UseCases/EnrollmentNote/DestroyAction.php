<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\EnrollmentNote;
use Illuminate\Support\Facades\DB;

/**
 * 受講登録に紐づくコーチメモを削除するユースケース。
 *
 * 削除条件の判定は Policy で行う前提。
 * メモ削除後に履歴を残す要件はない。
 */
final class DestroyAction
{
    public function __invoke(EnrollmentNote $note): void
    {
        DB::transaction(fn () => $note->delete());
    }
}
