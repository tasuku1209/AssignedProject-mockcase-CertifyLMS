<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\User;

final class MarkAllAsReadAction
{
    public function __invoke(User $auth): void
    {
        $auth->unreadNotifications()->update([
            'read_at' => now(),
        ]);
    }
}
