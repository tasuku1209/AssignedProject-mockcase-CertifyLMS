<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;

/**
 * 個人目標の認可ポリシー。
 *
 * - 個人目標の追加 / 編集 / 削除 / 達成・解除は受講生本人のみ
 * - 対象の Enrollment が本人のものであることを確認する
 * - 目標操作は学習中(Learning)の Enrollment に対してのみ許可する
 *
 * coach / admin は Enrollment 詳細画面から目標を閲覧できるが、
 * 個人目標に対する操作権限は持たない。
 */
class EnrollmentGoalPolicy
{
    /**
     * 受講登録に個人目標を追加できるか。
     */
    public function create(User $user, Enrollment $enrollment): bool
    {
        return $user->role === UserRole::Student
            && $enrollment->user_id === $user->id
            && $enrollment->status === EnrollmentStatus::Learning;
    }

    /**
     * 個人目標を編集できるか。
     */
    public function update(User $user, EnrollmentGoal $goal): bool
    {
        return $this->canManage($user, $goal);
    }

    /**
     * 個人目標を削除できるか。
     */
    public function delete(User $user, EnrollmentGoal $goal): bool
    {
        return $this->canManage($user, $goal);
    }

    /**
     * 個人目標を達成済みにできるか。
     */
    public function markAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->canManage($user, $goal)
            && $goal->achieved_at === null;
    }

    /**
     * 個人目標の達成を解除できるか。
     */
    public function unmarkAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->canManage($user, $goal)
            && $goal->achieved_at !== null;
    }

    /**
     * 目標の管理操作が可能か。
     *
     * - 受講生本人である
     * - 親 Enrollment が本人のものである
     * - Enrollment が learning 状態である
     */
    private function canManage(User $user, EnrollmentGoal $goal): bool
    {
        $enrollment = $goal->enrollment;

        return $user->role === UserRole::Student
            && $enrollment->user_id === $user->id
            && $enrollment->status === EnrollmentStatus::Learning;
    }
}
