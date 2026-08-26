<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateProfileRequest;
use App\UseCases\Settings\UpdateProfileAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * プロフィール設定画面を表示する。
     */
    public function edit(Request $request): View
    {
        $this->authorize('settings.profile.view');

        return view('settings.profile', [
            'user' => $request->user(),
        ]);
    }

    public function updateProfile(
        UpdateProfileRequest $request,
        UpdateProfileAction $action,
    ): RedirectResponse {
        $action($request->user(), $request->validated());

        return redirect()
            ->route('settings.profile.edit')
            ->with('success', 'プロフィールを更新しました。');
    }
}
