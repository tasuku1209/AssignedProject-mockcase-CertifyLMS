<?php

declare(strict_types=1);

namespace App\Services;

use Google\Client;

class GoogleCalendarClientFactory
{
    /**
     * Google API Client を生成する。
     */
    public function create(): Client
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
