<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class IndexAction
{
    /**
     * 通知一覧を取得する。
     *
     * - all: 自分宛の全通知
     * - unread: 自分宛の未読通知のみ
     * - 未読件数もあわせて取得する
     *
     * @return array{
     *     notifications: LengthAwarePaginator,
     *     unreadCount: int,
     *     tab: string
     * }
     */
    public function __invoke(
        User $auth,
        string $tab = 'all',
        int $perPage = 20,
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
            ->paginate($perPage)
            ->withQueryString();

        $unreadCount = $auth
            ->unreadNotifications()
            ->count();

        return [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'tab' => $tab,
        ];
    }
}
