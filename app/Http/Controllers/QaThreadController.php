<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\QaThreadStatus;
use App\Http\Requests\QaThread\IndexRequest;
use App\Models\Certification;
use App\UseCases\QaThread\IndexAction;
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
            'certifications' => Certification::published()->get(),
            'filters' => [
                'keyword' => $validated['keyword'] ?? '',
                'status' => $validated['status'] ?? '',
                'certification_id' => $validated['certification_id'] ?? '',
            ],
        ]);
    }
}
