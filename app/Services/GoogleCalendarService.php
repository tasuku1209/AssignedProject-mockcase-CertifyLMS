<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GoogleCalendar\GoogleOAuthTokenException;
use App\Models\GoogleCredential;
use App\Models\User;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\FreeBusyRequest;
use Google\Service\Calendar\FreeBusyRequestItem;
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
     * 指定Google Calendarの指定期間内のbusy時間を取得する。
     *
     * @return array<int, array{start: Carbon, end: Carbon}>
     */
    public function getBusyPeriods(
        GoogleCredential $credential,
        Carbon $timeMin,
        Carbon $timeMax,
    ): array {
        $client = $this->createAuthenticatedClient($credential);

        $calendarService = new Calendar($client);

        $request = new FreeBusyRequest;

        $request->setTimeMin($timeMin->toRfc3339String());
        $request->setTimeMax($timeMax->toRfc3339String());

        $item = new FreeBusyRequestItem;
        $item->setId($credential->calendar_id);

        $request->setItems([$item]);

        $response = $calendarService->freebusy->query($request);

        $calendar = $response->getCalendars()[$credential->calendar_id] ?? null;

        if ($calendar === null) {
            return [];
        }

        return collect($calendar->getBusy() ?? [])
            ->map(fn ($period) => [
                'start' => Carbon::parse($period->getStart()),
                'end' => Carbon::parse($period->getEnd()),
            ])
            ->all();
    }

    public function createEvent(
        GoogleCredential $credential,
        string $summary,
        Carbon $start,
        Carbon $end,
        ?string $meetingUrl,
    ): string {
        $client = $this->createAuthenticatedClient($credential);

        $calendarService = new Calendar($client);

        $event = new Event;

        $event->setSummary($summary);

        $startDateTime = new EventDateTime;
        $startDateTime->setDateTime($start->toRfc3339String());
        $startDateTime->setTimeZone(config('app.timezone'));

        $event->setStart($startDateTime);

        $endDateTime = new EventDateTime;
        $endDateTime->setDateTime($end->toRfc3339String());
        $endDateTime->setTimeZone(config('app.timezone'));

        $event->setEnd($endDateTime);

        if ($meetingUrl !== null) {
            $event->setDescription(
                "面談URL: {$meetingUrl}"
            );
        }

        $createdEvent = $calendarService->events->insert(
            $credential->calendar_id,
            $event,
        );

        return $createdEvent->getId();
    }

    /**
     * Google Calendar APIで利用可能な認証済みClientを生成する。
     *
     * access tokenが期限切れの場合はrefresh tokenで更新し、
     * 更新後のtoken情報をGoogleCredentialへ保存する。
     */
    private function createAuthenticatedClient(
        GoogleCredential $credential,
    ): Client {
        $client = $this->createClient();

        $client->setAccessToken([
            'access_token' => $credential->access_token,
            'refresh_token' => $credential->refresh_token,
        ]);

        if (
            $credential->token_expires_at !== null
            && $credential->token_expires_at->isPast()
            && $credential->refresh_token !== null
        ) {
            $token = $client->fetchAccessTokenWithRefreshToken(
                $credential->refresh_token,
            );

            if (isset($token['error'])) {
                throw new GoogleOAuthTokenException;
            }

            $credential->update([
                'access_token' => $token['access_token'],
                'token_expires_at' => isset($token['expires_in'])
                    ? now()->addSeconds((int) $token['expires_in'])
                    : null,
            ]);

            $client->setAccessToken($token);
        }

        return $client;
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
            'https://www.googleapis.com/auth/calendar.calendars.readonly',
        ]);

        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }
}
