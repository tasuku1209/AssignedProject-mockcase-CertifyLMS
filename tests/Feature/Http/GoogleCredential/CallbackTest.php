<?php

declare(strict_types=1);

namespace Tests\Feature\Http\GoogleCredential;

use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CallbackTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Google OAuth callback の正常系で使用する state をセッションに設定する。
     */
    private function putValidState(User $coach): string
    {
        $state = 'valid-oauth-state';

        session([
            'google_calendar_oauth_state' => [
                'user_id' => $coach->id,
                'state' => $state,
            ],
        ]);

        return $state;
    }

    /**
     * Google Calendar Service の正常系モックを設定する。
     *
     * @return array{
     *     access_token: string,
     *     refresh_token: string,
     *     expires_in: int,
     * }
     */
    private function mockGoogleCalendarService(User $coach): array
    {
        $token = [
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'expires_in' => 3600,
        ];

        $mock = Mockery::mock(GoogleCalendarService::class);

        $mock->shouldReceive('verifyState')
            ->once()
            ->with(
                Mockery::on(
                    fn (User $user) => $user->is($coach)
                ),
                'valid-oauth-state',
            )
            ->andReturn(true);

        $mock->shouldReceive('fetchAccessToken')
            ->once()
            ->with('test-code')
            ->andReturn($token);

        $mock->shouldReceive('getPrimaryCalendarId')
            ->once()
            ->with($token)
            ->andReturn('primary-calendar-id');

        $this->app->instance(GoogleCalendarService::class, $mock);

        return $token;
    }

    public function test_coach_can_complete_google_oauth_callback(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $state = $this->putValidState($coach);
        $token = $this->mockGoogleCalendarService($coach);

        // Act
        $response = $this->actingAs($coach)->get(
            route('settings.google-calendar.callback', [
                'code' => 'test-code',
                'state' => $state,
            ])
        );

        // Assert
        $response->assertRedirect();

        $this->assertDatabaseHas('google_credentials', [
            'user_id' => $coach->id,
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'],
            'calendar_id' => 'primary-calendar-id',
        ]);
    }

    public function test_successful_callback_redirects_to_availability_settings(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $state = $this->putValidState($coach);
        $this->mockGoogleCalendarService($coach);

        // Act & Assert
        $this->actingAs($coach)
            ->get(route('settings.google-calendar.callback', [
                'code' => 'test-code',
                'state' => $state,
            ]))
            ->assertRedirectToRoute('settings.availability.index');
    }

    public function test_successful_callback_sets_success_flash_message(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $state = $this->putValidState($coach);
        $this->mockGoogleCalendarService($coach);

        // Act & Assert
        $this->actingAs($coach)
            ->get(route('settings.google-calendar.callback', [
                'code' => 'test-code',
                'state' => $state,
            ]))
            ->assertSessionHas(
                'success',
                'Google Calendarを連携しました。'
            );
    }

    public function test_invalid_oauth_state_is_rejected(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        session([
            'google_calendar_oauth_state' => [
                'user_id' => $coach->id,
                'state' => 'valid-oauth-state',
            ],
        ]);

        $mock = Mockery::mock(GoogleCalendarService::class);

        $mock->shouldReceive('verifyState')
            ->once()
            ->with(
                Mockery::on(
                    fn (User $user) => $user->is($coach)
                ),
                'invalid-oauth-state',
            )
            ->andReturn(false);

        // state検証で終了するため、後続処理は呼ばれないことを確認する。
        $mock->shouldNotReceive('fetchAccessToken');
        $mock->shouldNotReceive('getPrimaryCalendarId');

        $this->app->instance(GoogleCalendarService::class, $mock);

        // Act & Assert
        $this->actingAs($coach)
            ->get(route('settings.google-calendar.callback', [
                'code' => 'test-code',
                'state' => 'invalid-oauth-state',
            ]))
            ->assertForbidden();

        $this->assertDatabaseCount('google_credentials', 0);
    }

    public function test_non_coach_cannot_handle_google_oauth_callback(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act & Assert
        $this->actingAs($student)
            ->get(route('settings.google-calendar.callback', [
                'code' => 'test-code',
                'state' => 'test-state',
            ]))
            ->assertForbidden();

        $this->assertDatabaseCount('google_credentials', 0);
    }
}
