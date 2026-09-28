<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\EnrollmentNote;
use Illuminate\Support\Facades\DB;

/**
 * 受講登録に紐づくコーチメモを更新するユースケース。
 *
 * 認可は Controller / Request の Policy で済ませる前提。
 * 更新対象はメモ本文のみ。
 */
final class UpdateAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(
        EnrollmentNote $note,
        array $validated,
    ): EnrollmentNote {
        return DB::transaction(function () use ($note, $validated) {
            $note->update([
                'body' => $validated['body'],
            ]);

            return $note->fresh();
        });
    }
}
