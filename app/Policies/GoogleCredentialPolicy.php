<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\GoogleCredential;
use App\Models\User;

/**
 * Google Calendar 連携の認可ルール。
 * Google Calendar 連携は active な coach のみ操作可能。
 */
class GoogleCredentialPolicy
{
    /**
     * Google Calendar 連携を開始できるか。
     */
    public function connect(User $auth): bool
    {
        return $auth->role === UserRole::Coach;
    }

    /**
     * Google Calendar OAuth callback を受け付けられるか。
     */
    public function callback(User $auth): bool
    {
        return $auth->role === UserRole::Coach;
    }

    /**
     * Google Calendar 連携を解除できるか。
     */
    public function delete(User $auth, GoogleCredential $credential): bool
    {
        return $auth->role === UserRole::Coach
            && $auth->id === $credential->user_id;
    }
}
