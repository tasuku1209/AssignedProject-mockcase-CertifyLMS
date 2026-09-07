<?php

declare(strict_types=1);

namespace App\Http\Controllers\Announcement;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\UseCases\Announcement\IndexAction;
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
}
