<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\QaThreadStatus;
use App\Http\Requests\QaThread\IndexRequest;
use App\Http\Requests\QaThread\StoreRequest;
use App\Models\Certification;
use App\Models\QaThread;
use App\UseCases\QaThread\IndexAction;
use App\UseCases\QaThread\ShowAction;
use App\UseCases\QaThread\StoreAction;
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
}
