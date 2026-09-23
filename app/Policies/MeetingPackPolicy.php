<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\MeetingPack;
use App\Models\User;

class MeetingPackPolicy
{
    /**
     * 面談パック管理画面の一覧を閲覧できるか。
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * 面談パックの詳細を閲覧できるか。
     */
    public function view(User $user, MeetingPack $meetingPack): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * 面談パックを新規作成できるか。
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * 面談パックを編集できるか。
     */
    public function update(User $user, MeetingPack $meetingPack): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * 面談パックを削除できるか。
     */
    public function delete(User $user, MeetingPack $meetingPack): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * 面談パックを公開できるか。
     */
    public function publish(User $user, MeetingPack $meetingPack): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * 面談パックをアーカイブできるか。
     */
    public function archive(User $user, MeetingPack $meetingPack): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * アーカイブ済みの面談パックを下書きへ戻せるか。
     */
    public function unarchive(User $user, MeetingPack $meetingPack): bool
    {
        return $user->role === UserRole::Admin;
    }
}
