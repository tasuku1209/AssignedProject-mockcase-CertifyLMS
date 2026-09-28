<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * 受講生本人の面談回数履歴閲覧に関する認可。
 */
class MeetingQuotaPolicy
{
    /**
     * 面談回数履歴の閲覧。本人のみ可。
     */
    public function viewHistory(User $auth, User $target): bool
    {
        return $auth->id === $target->id;
    }

    /**
     * 追加面談購入画面の閲覧。
     */
    public function viewCheckout(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    /**
     * 追加面談購入の開始。
     */
    public function createCheckout(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    /**
     * 購入完了画面の閲覧。
     */
    public function viewSuccess(User $user): bool
    {
        return $user->role === UserRole::Student;
    }
}
