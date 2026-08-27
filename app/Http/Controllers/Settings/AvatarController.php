<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Avatar\StoreRequest;
use App\UseCases\Avatar\StoreAction;
use Illuminate\Http\RedirectResponse;

class AvatarController extends Controller
{
    public function store(
        StoreRequest $request,
        StoreAction $action,
    ): RedirectResponse {
        $action(
            $request->user(),
            $request->file('avatar'),
        );

        return redirect()
            ->route('settings.profile.edit')
            ->with('success', 'アバター画像を更新しました。');
    }
}
