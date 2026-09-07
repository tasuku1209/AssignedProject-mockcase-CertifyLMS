<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\UseCases\Announcement\IndexAction;
use App\UseCases\Announcement\ShowAction;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    /**
     * お知らせ配信履歴一覧を表示する。
     */
    public function index(IndexAction $action): View
    {
        $this->authorize('viewAny', Announcement::class);

        $announcements = $action();

        return view(
            'announcement.management.index',
            compact('announcements')
        );
    }

    /**
     * 配信済みお知らせの詳細を表示する。
     */
    public function show(
        Announcement $announcement,
        ShowAction $action,
    ): View {
        $this->authorize('view', $announcement);

        $announcement = $action($announcement);

        return view(
            'announcement.management.show',
            compact('announcement')
        );
    }
}
