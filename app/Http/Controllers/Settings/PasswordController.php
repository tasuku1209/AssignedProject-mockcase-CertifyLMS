<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Fortify\UpdateUserPassword;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PasswordController extends Controller
{
    public function update(
        Request $request,
        UpdateUserPassword $action,
    ): RedirectResponse {
        $user = $request->user();

        $this->authorize('settings.password.update', $user);

        $action->update($user, $request->all());

        return redirect()
            ->route('settings.profile.edit')
            ->with('success', 'パスワードを変更しました。');
    }
}
