<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    /**
     * お知らせ一覧を閲覧できるか。
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * お知らせ詳細を閲覧できるか。
     */
    public function view(User $user, Announcement $announcement): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * お知らせを作成・配信できるか。
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
