<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use Illuminate\Notifications\DatabaseNotification;

final class MarkAsReadAction
{
    public function __invoke(DatabaseNotification $notification): void
    {
        $notification->markAsRead();
    }
}
