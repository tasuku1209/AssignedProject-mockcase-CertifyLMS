<?php

declare(strict_types=1);

namespace App\UseCases\Notification\Api\V1;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;

final class IndexAction
{
    /**
     * 通知ポップオーバー用の通知一覧を取得する。
     *
     * @return array{
     *     notifications: Collection<int, DatabaseNotification>,
     *     unreadCount: int
     * }
     */
    public function __invoke(
        User $auth,
        string $tab = 'all',
        int $limit = 10,
    ): array {
        if (! in_array($tab, ['all', 'unread'], true)) {
            abort(404);
        }

        $query = $auth->notifications();

        if ($tab === 'unread') {
            $query->whereNull('read_at');
        }

        $notifications = $query
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $unreadCount = $auth
            ->unreadNotifications()
            ->count();

        return [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ];
    }
}
