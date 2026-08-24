<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\UseCases\Notification\IndexAction;
use App\UseCases\Notification\MarkAllAsReadAction;
use App\UseCases\Notification\MarkAsReadAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request, IndexAction $action): View
    {
        $this->authorize('viewAny', DatabaseNotification::class);

        $result = $action(
            $request->user(),
            $request->query('tab', 'all'),
        );

        return view('notifications.index', $result);
    }

    public function markAsRead(
        DatabaseNotification $notification,
        MarkAsReadAction $action,
    ): RedirectResponse {
        $this->authorize('markAsRead', $notification);

        $action($notification);

        return redirect()->back();
    }

    public function markAllAsRead(
        Request $request,
        MarkAllAsReadAction $action,
    ): RedirectResponse {
        $this->authorize('markAllAsRead', $request->user());

        $action($request->user());

        return redirect()->back();
    }
}
