<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Database Notification に対する認可ポリシー。
 *
 * - view: 受講生 / コーチであれば通知一覧を閲覧可
 *   （自分宛の通知のみ取得する制御は Action 側で行う）
 * - markAsRead: 受講生 / コーチかつ、自分宛の通知のみ既読化可
 * - markAllAsRead: 受講生 / コーチであれば一括既読可
 *   （自分の通知のみ更新する制御は Action 側で行う）
 */
class NotificationPolicy
{
    /**
     * 通知一覧を閲覧できるか。
     *
     * 自分宛の通知のみを取得する制御は Action 側で行う。
     */
    public function viewAny(User $user): bool
    {
        return in_array(
            $user->role,
            [UserRole::Student, UserRole::Coach],
            true
        );
    }

    /**
     * 通知を既読化できるか。
     *
     * URL の {notification} で指定された通知が、
     * ログインユーザー本人宛の通知であることを確認する。
     */
    public function markAsRead(
        User $user,
        DatabaseNotification $notification
    ): bool {
        return in_array(
            $user->role,
            [UserRole::Student, UserRole::Coach],
            true
        )
            && $notification->notifiable_type === User::class
            && $notification->notifiable_id === $user->id;
    }

    /**
     * 通知をまとめて既読化できるか。
     *
     * 対象となる通知は Action 側でログインユーザー自身の通知に限定する。
     */
    public function markAllAsRead(User $user): bool
    {
        return in_array(
            $user->role,
            [UserRole::Student, UserRole::Coach],
            true
        );
    }
}
