<?php

declare(strict_types=1);

namespace Tests\Feature\Http\GoogleCredential;

use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_can_redirect_to_google_oauth(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        $authorizationUrl = 'https://accounts.google.com/o/oauth2/auth?test=1';

        $mock = Mockery::mock(GoogleCalendarService::class);

        $mock->shouldReceive('createAuthorizationUrl')
            ->once()
            ->with(
                Mockery::on(
                    fn (User $user) => $user->is($coach)
                )
            )
            ->andReturn($authorizationUrl);

        $this->app->instance(GoogleCalendarService::class, $mock);

        // Act
        $response = $this->actingAs($coach)
            ->get(route('settings.google-calendar.redirect'));

        // Assert
        $response->assertRedirect($authorizationUrl);
    }

    public function test_non_coach_cannot_connect_google_calendar(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act & Assert
        $this->actingAs($student)
            ->get(route('settings.google-calendar.redirect'))
            ->assertForbidden();
    }
}
