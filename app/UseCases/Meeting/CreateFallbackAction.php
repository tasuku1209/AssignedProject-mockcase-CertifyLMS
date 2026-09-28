<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\EnrollmentStatus;
use App\Models\User;
use Illuminate\Support\Collection;

final class CreateFallbackAction
{
    /**
     * 面談予約画面の fallback 用 Enrollment を取得するユースケース。
     */
    public function __invoke(User $user): Collection
    {
        return $user
            ->enrollments()
            ->whereIn('status', [
                EnrollmentStatus::Learning->value,
                EnrollmentStatus::Passed->value,
            ])
            ->with('certification')
            ->get();
    }
}
