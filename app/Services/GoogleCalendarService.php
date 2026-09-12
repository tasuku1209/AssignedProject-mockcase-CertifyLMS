<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GoogleCalendar\GoogleOAuthTokenException;
use App\Models\User;
use Google\Client;
use Google\Service\Calendar;
use Illuminate\Support\Str;

final class GoogleCalendarService
{
    /**
     * Google Calendar OAuth 認証画面のURLを生成する。
     */
    public function createAuthorizationUrl(User $user): string
    {
        $client = $this->createClient();

        $state = Str::random(64);

        session([
            'google_calendar_oauth_state' => [
                'user_id' => $user->id,
                'state' => $state,
            ],
        ]);

        $client->setState($state);

        return $client->createAuthUrl();
    }

    /**
     * OAuth callback の state が正当なものか検証する。
     */
    public function verifyState(User $user, string $state): bool
    {
        $stored = session('google_calendar_oauth_state');

        if (! is_array($stored)) {
            return false;
        }

        $storedUserId = $stored['user_id'] ?? null;
        $storedState = $stored['state'] ?? null;

        if (
            ! is_string($storedUserId)
            || $storedUserId !== $user->id
            || ! is_string($storedState)
            || ! hash_equals($storedState, $state)
        ) {
            return false;
        }

        session()->forget('google_calendar_oauth_state');

        return true;
    }

    /**
     * OAuth authorization code から Google の access token を取得する。
     *
     * @return array<string, mixed>
     */
    public function fetchAccessToken(string $code): array
    {
        $client = $this->createClient();

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new GoogleOAuthTokenException;
        }

        return $token;
    }

    /**
     * Primary Calendar の ID を取得する。
     */
    public function getPrimaryCalendarId(array $token): string
    {
        $client = $this->createClient();
        $client->setAccessToken($token);

        $calendarService = new Calendar($client);

        $calendar = $calendarService->calendars->get('primary');

        return $calendar->getId();
    }

    /**
     * Google API Client を生成する。
     */
    private function createClient(): Client
    {
        $client = new Client;

        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(
            route('settings.google-calendar.callback')
        );

        $client->setScopes([
            'https://www.googleapis.com/auth/calendar.events.owned',
            'https://www.googleapis.com/auth/calendar.events.freebusy',
        ]);

        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }
}
