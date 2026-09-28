<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\UseCases\Notification\Api\V1\IndexAction;
use App\UseCases\Notification\MarkAllAsReadAction;
use App\UseCases\Notification\MarkAsReadAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * 通知一覧を取得する。
     */
    public function index(
        Request $request,
        IndexAction $action,
    ): AnonymousResourceCollection {
        $this->authorize('viewAny', DatabaseNotification::class);

        $result = $action(
            $request->user(),
            $request->query('tab', 'all'),
        );

        return NotificationResource::collection($result['notifications'])
            ->additional([
                'unread_count' => $result['unreadCount'],
            ]);
    }

    /**
     * 通知を既読にする。
     */
    public function markAsRead(
        Request $request,
        DatabaseNotification $notification,
        MarkAsReadAction $action,
    ): JsonResponse {
        $this->authorize('markAsRead', $notification);

        $action($notification);

        return response()->json([
            'message' => '通知を既読にしました。',
            'unread_count' => $request->user()
                ->unreadNotifications()
                ->count(),
        ]);
    }

    /**
     * すべての通知を既読にする。
     */
    public function markAllAsRead(
        Request $request,
        MarkAllAsReadAction $action,
    ): JsonResponse {
        $this->authorize('markAllAsRead', DatabaseNotification::class);

        $action($request->user());

        return response()->json([
            'message' => 'すべての通知を既読にしました。',
            'unread_count' => 0,
        ]);
    }
}
