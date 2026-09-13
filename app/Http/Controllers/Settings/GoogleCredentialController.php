<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Exceptions\GoogleCalendar\InvalidOAuthStateException;
use App\Http\Controllers\Controller;
use App\Models\GoogleCredential;
use App\Services\GoogleCalendarService;
use App\UseCases\GoogleCredential\DestroyAction;
use App\UseCases\GoogleCredential\StoreAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleCredentialController extends Controller
{
    /**
     * Google Calendar OAuth 認証画面へリダイレクトする。
     */
    public function redirect(
        Request $request,
        GoogleCalendarService $service,
    ): RedirectResponse {
        $this->authorize('connect', GoogleCredential::class);

        return redirect()->away(
            $service->createAuthorizationUrl($request->user())
        );
    }

    /**
     * Google Calendar OAuth コールバックを処理する。
     */
    public function callback(
        Request $request,
        GoogleCalendarService $service,
        StoreAction $action,
    ): RedirectResponse {
        $this->authorize('callback', GoogleCredential::class);

        $user = $request->user();
        $code = $request->string('code')->toString();
        $state = $request->string('state')->toString();

        if (! $service->verifyState($user, $state)) {
            throw new InvalidOAuthStateException;
        }

        $token = $service->fetchAccessToken($code);

        $calendarId = $service->getPrimaryCalendarId($token);

        $action(
            user: $user,
            token: $token,
            calendarId: $calendarId,
        );

        return redirect()
            ->route('settings.availability.index')
            ->with('success', 'Google Calendarを連携しました。');
    }

    /**
     * Google Calendar の連携を解除する。
     */
    public function destroy(
        Request $request,
        DestroyAction $action,
    ): RedirectResponse {
        $credential = $request->user()
            ->googleCredential()
            ->firstOrFail();

        $this->authorize('delete', $credential);

        $action($credential);

        return redirect()
            ->route('settings.availability.index')
            ->with('success', 'Google Calendarの連携を解除しました。');
    }
}
