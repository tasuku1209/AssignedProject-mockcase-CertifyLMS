<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 受講登録にコーチメモを新規作成するユースケース。
 *
 * 認可は Controller / Policy で済ませる前提。
 * 作成者にはログインユーザーを記録する。
 */
final class StoreAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(
        User $auth,
        Enrollment $enrollment,
        array $validated,
    ): EnrollmentNote {
        return DB::transaction(fn () => EnrollmentNote::create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $auth->id,
            'body' => $validated['body'],
        ]));
    }
}
