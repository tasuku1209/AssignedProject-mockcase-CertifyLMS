<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\GoogleCalendar\GoogleOAuthTokenException;
use App\Models\GoogleCredential;
use App\Models\User;
use App\Services\GoogleCalendarClientFactory;
use App\Services\GoogleCalendarService;
use Google\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('external-api')]
class GoogleCalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_authorization_url_returns_google_oauth_url_and_stores_state(): void
    {
        // Arrange
        $user = User::factory()->coach()->create();

        $googleClient = Mockery::mock(Client::class);

        $googleClient->shouldReceive('setState')
            ->once()
            ->with(Mockery::type('string'));

        $googleClient->shouldReceive('createAuthUrl')
            ->once()
            ->andReturn('https://accounts.google.com/o/oauth2/auth');

        $factory = $this->mock(GoogleCalendarClientFactory::class);

        $factory->shouldReceive('create')
            ->once()
            ->andReturn($googleClient);

        $this->app->instance(
            GoogleCalendarClientFactory::class,
            $factory,
        );

        $service = app(GoogleCalendarService::class);

        // Act
        $authorizationUrl = $service->createAuthorizationUrl($user);

        // Assert
        $this->assertSame(
            'https://accounts.google.com/o/oauth2/auth',
            $authorizationUrl,
        );

        $oauthState = session('google_calendar_oauth_state');

        $this->assertIsArray($oauthState);
        $this->assertSame($user->id, $oauthState['user_id']);
        $this->assertIsString($oauthState['state']);
        $this->assertSame(64, strlen($oauthState['state']));
    }

    public function test_verify_state_returns_true_and_forgets_state_when_valid(): void
    {
        // Arrange
        $user = User::factory()->coach()->create();

        $state = 'test-oauth-state';

        session([
            'google_calendar_oauth_state' => [
                'user_id' => $user->id,
                'state' => $state,
            ],
        ]);

        $service = app(GoogleCalendarService::class);

        // Act
        $result = $service->verifyState($user, $state);

        // Assert
        $this->assertTrue($result);
        $this->assertNull(
            session('google_calendar_oauth_state'),
        );
    }

    public function test_verify_state_returns_false_when_oauth_state_is_not_stored(): void
    {
        // Arrange
        $user = User::factory()->coach()->create();

        $service = app(GoogleCalendarService::class);

        // Act
        $result = $service->verifyState($user, 'test-oauth-state');

        // Assert
        $this->assertFalse($result);
    }

    public function test_verify_state_returns_false_when_user_id_does_not_match(): void
    {
        // Arrange
        $storedUser = User::factory()->coach()->create();
        $currentUser = User::factory()->coach()->create();

        session([
            'google_calendar_oauth_state' => [
                'user_id' => $storedUser->id,
                'state' => 'test-oauth-state',
            ],
        ]);

        $service = app(GoogleCalendarService::class);

        // Act
        $result = $service->verifyState(
            $currentUser,
            'test-oauth-state',
        );

        // Assert
        $this->assertFalse($result);

        $this->assertNotNull(
            session('google_calendar_oauth_state'),
        );
    }

    public function test_verify_state_returns_false_when_state_does_not_match(): void
    {
        // Arrange
        $user = User::factory()->coach()->create();

        session([
            'google_calendar_oauth_state' => [
                'user_id' => $user->id,
                'state' => 'stored-state',
            ],
        ]);

        $service = app(GoogleCalendarService::class);

        // Act
        $result = $service->verifyState(
            $user,
            'different-state',
        );

        // Assert
        $this->assertFalse($result);

        $this->assertNotNull(
            session('google_calendar_oauth_state'),
        );
    }

    public function test_verify_state_returns_false_when_stored_user_id_is_not_string(): void
    {
        // Arrange
        $user = User::factory()->coach()->create();

        session([
            'google_calendar_oauth_state' => [
                'user_id' => 123,
                'state' => 'test-oauth-state',
            ],
        ]);

        $service = app(GoogleCalendarService::class);

        // Act
        $result = $service->verifyState(
            $user,
            'test-oauth-state',
        );

        // Assert
        $this->assertFalse($result);
    }

    public function test_verify_state_returns_false_when_stored_state_is_not_string(): void
    {
        // Arrange
        $user = User::factory()->coach()->create();

        session([
            'google_calendar_oauth_state' => [
                'user_id' => $user->id,
                'state' => 123,
            ],
        ]);

        $service = app(GoogleCalendarService::class);

        // Act
        $result = $service->verifyState(
            $user,
            'test-oauth-state',
        );

        // Assert
        $this->assertFalse($result);
    }

    public function test_fetch_access_token_returns_access_token(): void
    {
        // Arrange
        $googleClient = Mockery::mock(Client::class);

        $googleClient->shouldReceive('fetchAccessTokenWithAuthCode')
            ->once()
            ->with('test-auth-code')
            ->andReturn([
                'access_token' => 'test-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]);

        $factory = $this->mock(GoogleCalendarClientFactory::class);

        $factory->shouldReceive('create')
            ->once()
            ->andReturn($googleClient);

        $this->app->instance(
            GoogleCalendarClientFactory::class,
            $factory,
        );

        $service = app(GoogleCalendarService::class);

        // Act
        $token = $service->fetchAccessToken('test-auth-code');

        // Assert
        $this->assertSame(
            [
                'access_token' => 'test-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ],
            $token,
        );
    }

    public function test_fetch_access_token_throws_exception_when_google_returns_error(): void
    {
        // Arrange
        $googleClient = Mockery::mock(Client::class);

        $googleClient->shouldReceive('fetchAccessTokenWithAuthCode')
            ->once()
            ->with('test-auth-code')
            ->andReturn([
                'error' => 'invalid_grant',
            ]);

        $factory = $this->mock(GoogleCalendarClientFactory::class);

        $factory->shouldReceive('create')
            ->once()
            ->andReturn($googleClient);

        $this->app->instance(
            GoogleCalendarClientFactory::class,
            $factory,
        );

        $service = app(GoogleCalendarService::class);

        // Act / Assert
        $this->expectException(GoogleOAuthTokenException::class);

        $service->fetchAccessToken('test-auth-code');
    }

    public function test_get_primary_calendar_id_returns_calendar_id(): void
    {
        // Arrange
        $token = [
            'access_token' => 'test-access-token',
            'token_type' => 'Bearer',
        ];

        $mockHandler = new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'id' => 'primary-calendar-id',
                    'summary' => 'Primary Calendar',
                ], JSON_THROW_ON_ERROR),
            ),
        ]);

        $httpClient = new GuzzleClient([
            'handler' => HandlerStack::create($mockHandler),
        ]);

        $googleClient = new Client;
        $googleClient->setClientId(config('services.google.client_id'));
        $googleClient->setClientSecret(config('services.google.client_secret'));
        $googleClient->setHttpClient($httpClient);

        $googleClient->setAccessToken($token);

        $factory = $this->mock(GoogleCalendarClientFactory::class);

        $factory->shouldReceive('create')
            ->once()
            ->andReturn($googleClient);

        $this->app->instance(
            GoogleCalendarClientFactory::class,
            $factory,
        );

        $service = app(GoogleCalendarService::class);

        // Act
        $calendarId = $service->getPrimaryCalendarId($token);

        // Assert
        $this->assertSame(
            'primary-calendar-id',
            $calendarId,
        );

        $this->assertSame(0, $mockHandler->count());
    }

    public function test_get_busy_periods_uses_mocked_google_api_response(): void
    {
        // Arrange
        $credential = GoogleCredential::factory()->create([
            'calendar_id' => 'primary',
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'token_expires_at' => now()->addHour(),
        ]);

        $mockHandler = new MockHandler([
            // OAuth認証用
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'access_token' => 'mocked-access-token',
                    'expires_in' => 3600,
                    'token_type' => 'Bearer',
                ], JSON_THROW_ON_ERROR),
            ),

            // Calendar FreeBusy API用
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'calendars' => [
                        'primary' => [
                            'busy' => [
                                [
                                    'start' => '2026-09-25T10:00:00+09:00',
                                    'end' => '2026-09-25T11:00:00+09:00',
                                ],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
            ),
        ]);

        $httpClient = new GuzzleClient([
            'handler' => HandlerStack::create($mockHandler),
        ]);

        $googleClient = new Client;
        $googleClient->setClientId(config('services.google.client_id'));
        $googleClient->setClientSecret(config('services.google.client_secret'));
        $googleClient->setHttpClient($httpClient);

        $factory = $this->mock(GoogleCalendarClientFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->andReturn($googleClient);

        $this->app->instance(
            GoogleCalendarClientFactory::class,
            $factory,
        );

        $service = app(GoogleCalendarService::class);

        // Act
        $busyPeriods = $service->getBusyPeriods(
            credential: $credential,
            timeMin: now()->setTime(9, 0),
            timeMax: now()->setTime(12, 0),
        );

        // Assert
        $this->assertCount(1, $busyPeriods);
        $this->assertSame(
            '2026-09-25 10:00:00',
            $busyPeriods[0]['start']->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            '2026-09-25 11:00:00',
            $busyPeriods[0]['end']->format('Y-m-d H:i:s'),
        );

        $this->assertSame(0, $mockHandler->count());
    }

    public function test_get_busy_periods_returns_empty_array_when_calendar_is_not_in_response(): void
    {
        // Arrange
        $credential = GoogleCredential::factory()->create([
            'calendar_id' => 'target-calendar-id',
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'token_expires_at' => now()->addHour(),
        ]);

        $mockHandler = new MockHandler([
            // OAuth認証用
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'access_token' => 'mocked-access-token',
                    'expires_in' => 3600,
                    'token_type' => 'Bearer',
                ], JSON_THROW_ON_ERROR),
            ),

            // Calendar FreeBusy API用
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'calendars' => [
                        'another-calendar-id' => [
                            'busy' => [],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
            ),
        ]);

        $httpClient = new GuzzleClient([
            'handler' => HandlerStack::create($mockHandler),
        ]);

        $googleClient = new Client;
        $googleClient->setClientId(config('services.google.client_id'));
        $googleClient->setClientSecret(config('services.google.client_secret'));
        $googleClient->setHttpClient($httpClient);

        $factory = $this->mock(GoogleCalendarClientFactory::class);

        $factory->shouldReceive('create')
            ->once()
            ->andReturn($googleClient);

        $this->app->instance(
            GoogleCalendarClientFactory::class,
            $factory,
        );

        $service = app(GoogleCalendarService::class);

        // Act
        $busyPeriods = $service->getBusyPeriods(
            credential: $credential,
            timeMin: now()->setTime(9, 0),
            timeMax: now()->setTime(12, 0),
        );

        // Assert
        $this->assertSame([], $busyPeriods);
        $this->assertSame(0, $mockHandler->count());
    }

    public function test_expired_token_is_refreshed_before_google_calendar_api_call(): void
    {
        // Arrange
        $credential = GoogleCredential::factory()->create([
            'calendar_id' => 'primary',
            'access_token' => 'expired-access-token',
            'refresh_token' => 'test-refresh-token',
            'token_expires_at' => now()->subHour(),
        ]);

        $mockHandler = new MockHandler([
            // Refresh token によるアクセストークン取得
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'access_token' => 'refreshed-access-token',
                    'expires_in' => 3600,
                    'token_type' => 'Bearer',
                ], JSON_THROW_ON_ERROR),
            ),

            // Calendar FreeBusy API
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'calendars' => [
                        'primary' => [
                            'busy' => [
                                [
                                    'start' => '2026-09-25T10:00:00+09:00',
                                    'end' => '2026-09-25T11:00:00+09:00',
                                ],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
            ),
        ]);

        $httpClient = new GuzzleClient([
            'handler' => HandlerStack::create($mockHandler),
        ]);

        $googleClient = new Client;
        $googleClient->setClientId(config('services.google.client_id'));
        $googleClient->setClientSecret(config('services.google.client_secret'));
        $googleClient->setHttpClient($httpClient);

        $factory = $this->mock(GoogleCalendarClientFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->andReturn($googleClient);

        $this->app->instance(
            GoogleCalendarClientFactory::class,
            $factory,
        );

        $service = app(GoogleCalendarService::class);

        // Act
        $busyPeriods = $service->getBusyPeriods(
            credential: $credential,
            timeMin: now()->setTime(9, 0),
            timeMax: now()->setTime(12, 0),
        );

        // Assert
        $this->assertCount(1, $busyPeriods);

        $this->assertSame(
            '2026-09-25 10:00:00',
            $busyPeriods[0]['start']->format('Y-m-d H:i:s'),
        );

        $this->assertSame(
            '2026-09-25 11:00:00',
            $busyPeriods[0]['end']->format('Y-m-d H:i:s'),
        );

        $credential->refresh();

        $this->assertSame(
            'refreshed-access-token',
            $credential->access_token,
        );

        $this->assertNotNull($credential->token_expires_at);
        $this->assertTrue($credential->token_expires_at->isFuture());

        $this->assertSame(0, $mockHandler->count());
    }

    public function test_expired_token_throws_exception_when_refresh_fails(): void
    {
        // Arrange
        $credential = GoogleCredential::factory()->create([
            'calendar_id' => 'primary',
            'access_token' => 'expired-access-token',
            'refresh_token' => 'test-refresh-token',
            'token_expires_at' => now()->subHour(),
        ]);

        $mockHandler = new MockHandler([
            // Refresh tokenによるアクセストークン取得失敗
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'error' => 'invalid_grant',
                    'error_description' => 'Token has been expired or revoked.',
                ], JSON_THROW_ON_ERROR),
            ),
        ]);

        $httpClient = new GuzzleClient([
            'handler' => HandlerStack::create($mockHandler),
        ]);

        $googleClient = new Client;
        $googleClient->setClientId(config('services.google.client_id'));
        $googleClient->setClientSecret(config('services.google.client_secret'));
        $googleClient->setHttpClient($httpClient);

        $factory = $this->mock(GoogleCalendarClientFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->andReturn($googleClient);

        $this->app->instance(
            GoogleCalendarClientFactory::class,
            $factory,
        );

        $service = app(GoogleCalendarService::class);

        // Act & Assert
        $this->expectException(
            GoogleOAuthTokenException::class,
        );

        $service->getBusyPeriods(
            credential: $credential,
            timeMin: now()->setTime(9, 0),
            timeMax: now()->setTime(12, 0),
        );
    }

    public function test_create_event_returns_google_event_id(): void
    {
        // Arrange
        $credential = GoogleCredential::factory()->create([
            'calendar_id' => 'primary',
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'token_expires_at' => now()->addHour(),
        ]);

        $mockHandler = new MockHandler([
            // OAuth関連通信
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'access_token' => 'test-access-token',
                    'expires_in' => 3600,
                    'token_type' => 'Bearer',
                ], JSON_THROW_ON_ERROR),
            ),

            // Calendar Events API
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'id' => 'google-event-123',
                ], JSON_THROW_ON_ERROR),
            ),
        ]);

        $httpClient = new GuzzleClient([
            'handler' => HandlerStack::create($mockHandler),
        ]);

        $googleClient = new Client;
        $googleClient->setClientId(config('services.google.client_id'));
        $googleClient->setClientSecret(config('services.google.client_secret'));
        $googleClient->setHttpClient($httpClient);

        $factory = $this->mock(GoogleCalendarClientFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->andReturn($googleClient);

        $this->app->instance(
            GoogleCalendarClientFactory::class,
            $factory,
        );

        $service = app(GoogleCalendarService::class);

        $start = now()->setTime(10, 0);
        $end = now()->setTime(11, 0);

        // Act
        $eventId = $service->createEvent(
            credential: $credential,
            summary: 'テスト面談',
            start: $start,
            end: $end,
            meetingUrl: 'https://example.com/meeting',
        );

        // Assert
        $this->assertSame(
            'google-event-123',
            $eventId,
        );

        $this->assertSame(0, $mockHandler->count());
    }

    public function test_create_event_succeeds_without_meeting_url(): void
    {
        // Arrange
        $credential = GoogleCredential::factory()->create([
            'calendar_id' => 'primary',
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'token_expires_at' => now()->addHour(),
        ]);

        $mockHandler = new MockHandler([
            // OAuth関連通信
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'access_token' => 'test-access-token',
                    'expires_in' => 3600,
                    'token_type' => 'Bearer',
                ], JSON_THROW_ON_ERROR),
            ),

            // Calendar Events API
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'id' => 'google-event-without-url',
                ], JSON_THROW_ON_ERROR),
            ),
        ]);

        $httpClient = new GuzzleClient([
            'handler' => HandlerStack::create($mockHandler),
        ]);

        $googleClient = new Client;
        $googleClient->setClientId(config('services.google.client_id'));
        $googleClient->setClientSecret(config('services.google.client_secret'));
        $googleClient->setHttpClient($httpClient);

        $factory = $this->mock(GoogleCalendarClientFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->andReturn($googleClient);

        $this->app->instance(
            GoogleCalendarClientFactory::class,
            $factory,
        );

        $service = app(GoogleCalendarService::class);

        $start = now()->setTime(10, 0);
        $end = now()->setTime(11, 0);

        // Act
        $eventId = $service->createEvent(
            credential: $credential,
            summary: 'テスト面談',
            start: $start,
            end: $end,
            meetingUrl: null,
        );

        // Assert
        $this->assertSame(
            'google-event-without-url',
            $eventId,
        );

        $this->assertSame(0, $mockHandler->count());
    }

    public function test_delete_event_sends_request_to_google_calendar_api(): void
    {
        // Arrange
        $credential = GoogleCredential::factory()->create([
            'calendar_id' => 'primary',
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'token_expires_at' => now()->addHour(),
        ]);

        $mockHandler = new MockHandler([
            // OAuth関連通信
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'access_token' => 'test-access-token',
                    'expires_in' => 3600,
                    'token_type' => 'Bearer',
                ], JSON_THROW_ON_ERROR),
            ),

            // Calendar Events Delete API
            new Response(
                204,
            ),
        ]);

        $httpClient = new GuzzleClient([
            'handler' => HandlerStack::create($mockHandler),
        ]);

        $googleClient = new Client;
        $googleClient->setClientId(config('services.google.client_id'));
        $googleClient->setClientSecret(config('services.google.client_secret'));
        $googleClient->setHttpClient($httpClient);

        $factory = $this->mock(GoogleCalendarClientFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->andReturn($googleClient);

        $this->app->instance(
            GoogleCalendarClientFactory::class,
            $factory,
        );

        $service = app(GoogleCalendarService::class);

        // Act
        $service->deleteEvent(
            credential: $credential,
            eventId: 'google-event-123',
        );

        // Assert
        $this->assertSame(0, $mockHandler->count());
    }
}
