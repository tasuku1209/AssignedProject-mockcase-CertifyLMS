<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Announcement\StoreRequest;
use App\Models\Announcement;
use App\UseCases\Announcement\CreateAction;
use App\UseCases\Announcement\IndexAction;
use App\UseCases\Announcement\ShowAction;
use App\UseCases\Announcement\StoreAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    /**
     * お知らせ配信履歴一覧を表示する。
     */
    public function index(IndexAction $action): View
    {
        $this->authorize('viewAny', Announcement::class);

        return view('announcement.management.index', [
            'announcements' => $action(),
        ]);
    }

    /**
     * 配信済みお知らせの詳細を表示する。
     */
    public function show(
        Announcement $announcement,
        ShowAction $action,
    ): View {
        $this->authorize('view', $announcement);

        return view('announcement.management.show', [
            'announcement' => $action($announcement),
        ]);
    }

    /**
     * お知らせ配信作成画面を表示する。
     */
    public function create(CreateAction $action): View
    {
        $this->authorize('create', Announcement::class);

        return view('announcement.management.create', $action());
    }

    /**
     * お知らせを作成して配信する。
     */
    public function store(
        StoreRequest $request,
        StoreAction $action,
    ): RedirectResponse {
        $announcement = $action(
            $request->user(),
            $request->validated(),
        );

        return redirect()
            ->route('admin.announcements.show', $announcement)
            ->with('success', 'お知らせを配信しました。');
    }
}
