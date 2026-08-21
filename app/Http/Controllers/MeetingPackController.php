<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MeetingPack\IndexRequest;
use App\Http\Requests\MeetingPack\StoreRequest;
use App\Http\Requests\MeetingPack\UpdateRequest;
use App\Models\MeetingPack;
use App\UseCases\MeetingPack\ArchiveAction;
use App\UseCases\MeetingPack\DestroyAction;
use App\UseCases\MeetingPack\IndexAction;
use App\UseCases\MeetingPack\PublishAction;
use App\UseCases\MeetingPack\ShowAction;
use App\UseCases\MeetingPack\StoreAction;
use App\UseCases\MeetingPack\UnarchiveAction;
use App\UseCases\MeetingPack\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * admin 用の面談パックマスタ管理画面 Controller。
 * CRUD + 状態遷移を提供する。
 */
class MeetingPackController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();

        $plans = $action(
            keyword: $validated['keyword'] ?? null,
            status: $validated['status'] ?? null,
        );

        return view('meeting-pack.management.index', [
            'plans' => $plans,
            'keyword' => $validated['keyword'] ?? '',
            'status' => $validated['status'] ?? '',
        ]);
    }

    public function show(MeetingPack $meetingPack, ShowAction $action): View
    {
        $this->authorize('view', $meetingPack);

        return view('meeting-pack.management.show', [
            'plan' => $action($meetingPack),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', MeetingPack::class);

        return view('meeting-pack.management.create');
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $meetingPack = $action($request->user(), $request->validated());

        return redirect()
            ->route('admin.meeting-packs.show', $meetingPack)
            ->with('success', '面談パックを作成しました。');
    }

    public function edit(MeetingPack $meetingPack): View
    {
        $this->authorize('update', $meetingPack);

        return view('meeting-pack.management.edit', [
            'plan' => $meetingPack,
        ]);
    }

    public function update(
        MeetingPack $meetingPack,
        UpdateRequest $request,
        UpdateAction $action
    ): RedirectResponse {
        $action(
            $meetingPack,
            $request->user(),
            $request->validated()
        );

        return redirect()
            ->route('admin.meeting-packs.show', $meetingPack)
            ->with('success', '面談パックを更新しました。');
    }

    public function destroy(
        MeetingPack $meetingPack,
        DestroyAction $action
    ): RedirectResponse {
        $this->authorize('delete', $meetingPack);

        $action($meetingPack);

        return redirect()
            ->route('admin.meeting-packs.index')
            ->with('success', '面談パックを削除しました。');
    }

    public function publish(
        MeetingPack $plan,
        PublishAction $action
    ): RedirectResponse {
        $this->authorize('publish', $plan);

        $action($plan, request()->user());

        return redirect()
            ->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックを公開しました。');
    }

    public function archive(
        MeetingPack $plan,
        ArchiveAction $action
    ): RedirectResponse {
        $this->authorize('archive', $plan);

        $action($plan, request()->user());

        return redirect()
            ->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックをアーカイブしました。');
    }

    public function unarchive(
        MeetingPack $plan,
        UnarchiveAction $action
    ): RedirectResponse {
        $this->authorize('unarchive', $plan);

        $action($plan, request()->user());

        return redirect()
            ->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックを下書きに戻しました。');
    }
}
