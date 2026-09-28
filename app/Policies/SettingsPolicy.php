<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * ユーザー設定に対する認可ポリシー。
 *
 * プロフィール / アバター / パスワード設定は、ログイン済みの全ロールが
 * 自身の設定に対してのみ操作できる。
 *
 * 受講生は卒業(graduated)状態でも設定を利用できる。
 */
class SettingsPolicy
{
    /**
     * プロフィール設定画面の閲覧権限。
     */
    public function view(User $user): bool
    {
        return $this->canManage($user);
    }

    /**
     * プロフィール情報の更新権限。
     */
    public function updateProfile(User $user): bool
    {
        return $this->canManage($user);
    }

    /**
     * アバター画像の登録・更新権限。
     */
    public function storeAvatar(User $user): bool
    {
        return $this->canManage($user);
    }

    /**
     * アバター画像の削除権限。
     */
    public function deleteAvatar(User $user): bool
    {
        return $this->canManage($user);
    }

    /**
     * パスワード変更の権限。
     */
    public function updatePassword(User $user): bool
    {
        return $this->canManage($user);
    }

    /**
     * 設定機能を利用できるロールか判定する。
     */
    private function canManage(User $user): bool
    {
        return in_array($user->role, [
            UserRole::Admin,
            UserRole::Coach,
            UserRole::Student,
        ], true);
    }
}
