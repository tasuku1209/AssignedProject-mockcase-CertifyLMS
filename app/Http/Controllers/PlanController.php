<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Plan\IndexRequest;
use App\Http\Requests\Plan\StoreRequest;
use App\Http\Requests\Plan\UpdateRequest;
use App\Models\Plan;
use App\UseCases\Plan\IndexAction;
use App\UseCases\Plan\ShowAction;
use App\UseCases\Plan\StoreAction;
use App\UseCases\Plan\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * admin 用のプランマスタ管理画面 Controller。
 */
class PlanController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();

        $plans = $action(
            keyword: $validated['keyword'] ?? null,
            status: $validated['status'] ?? null,
        );

        return view('plan.management.index', [
            'plans' => $plans,
            'keyword' => $validated['keyword'] ?? '',
            'status' => $validated['status'] ?? '',
        ]);
    }

    public function show(Plan $plan, ShowAction $action): View
    {
        $this->authorize('view', $plan);

        return view('plan.management.show', [
            'plan' => $action($plan),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Plan::class);

        return view('plan.management.create');
    }

    public function store(
        StoreRequest $request,
        StoreAction $action
    ): RedirectResponse {
        $plan = $action(
            $request->user(),
            $request->validated()
        );

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', '受講プランを作成しました。');
    }

    public function edit(Plan $plan): View
    {
        $this->authorize('update', $plan);

        return view('plan.edit', [
            'plan' => $plan,
        ]);
    }

    public function update(
        Plan $plan,
        UpdateRequest $request,
        UpdateAction $action
    ): RedirectResponse {
        $action(
            $plan,
            $request->user(),
            $request->validated()
        );

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', '受講プランを更新しました。');
    }
}
