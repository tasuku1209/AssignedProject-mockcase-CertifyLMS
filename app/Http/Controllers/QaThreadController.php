<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\QaThreadStatus;
use App\Http\Requests\QaThread\IndexAsAdminRequest;
use App\Http\Requests\QaThread\IndexRequest;
use App\Http\Requests\QaThread\StoreRequest;
use App\Http\Requests\QaThread\UpdateRequest;
use App\Models\Certification;
use App\Models\QaThread;
use App\UseCases\QaThread\DestroyAction;
use App\UseCases\QaThread\IndexAction;
use App\UseCases\QaThread\IndexAsAdminAction;
use App\UseCases\QaThread\ResolveAction;
use App\UseCases\QaThread\ShowAction;
use App\UseCases\QaThread\StoreAction;
use App\UseCases\QaThread\UnresolveAction;
use App\UseCases\QaThread\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class QaThreadController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();

        $threads = $action(
            keyword: $validated['keyword'] ?? null,
            status: isset($validated['status'])
                ? QaThreadStatus::from($validated['status'])
                : null,
            certificationId: $validated['certification_id'] ?? null,
        );

        return view('qa-thread.index', [
            'threads' => $threads,
            'certifications' => Certification::published()
                ->orderByDesc('updated_at')
                ->get(),
            'filters' => [
                'keyword' => $validated['keyword'] ?? '',
                'status' => $validated['status'] ?? '',
                'certification_id' => $validated['certification_id'] ?? '',
            ],
        ]);
    }

    public function indexAsAdmin(IndexAsAdminRequest $request, IndexAsAdminAction $action): View
    {
        $validated = $request->validated();

        $threads = $action(
            keyword: $validated['keyword'] ?? null,
            status: isset($validated['status'])
                ? QaThreadStatus::from($validated['status'])
                : null,
            certificationId: $validated['certification_id'] ?? null,
        );

        return view('qa-thread.index', [
            'threads' => $threads,
            'certifications' => Certification::query()
                ->orderByDesc('updated_at')
                ->get(),
            'filters' => [
                'keyword' => $validated['keyword'] ?? '',
                'status' => $validated['status'] ?? '',
                'certification_id' => $validated['certification_id'] ?? '',
            ],
            'indexRoute' => 'admin.qa-board.index',
        ]);
    }

    public function show(QaThread $thread, ShowAction $action): View
    {
        $this->authorize('view', $thread);

        return view('qa-thread.show', [
            'thread' => $action($thread),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', QaThread::class);

        return view('qa-thread.create', [
            'certifications' => Certification::published()
                ->orderByDesc('updated_at')
                ->get(),
        ]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $thread = $action(
            $request->user(),
            $request->validated()
        );

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を投稿しました。');
    }

    public function edit(QaThread $thread): View
    {
        $this->authorize('update', $thread);

        return view('qa-thread.edit', [
            'thread' => $thread,
        ]);
    }

    public function update(QaThread $thread, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($thread, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を更新しました。');
    }

    public function destroy(QaThread $thread, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $thread);

        $action($thread, request()->user());

        $indexRoute = request()->routeIs('admin.*')
            ? 'admin.qa-board.index'
            : 'qa-board.index';

        return redirect()
            ->route($indexRoute)
            ->with('success', '質問を削除しました。');
    }

    public function resolve(QaThread $thread, ResolveAction $action): RedirectResponse
    {
        $this->authorize('resolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を解決済みにしました。');
    }

    public function unresolve(QaThread $thread, UnresolveAction $action): RedirectResponse
    {
        $this->authorize('unresolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を未解決に戻しました。');
    }
}
