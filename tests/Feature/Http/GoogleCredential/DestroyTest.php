<?php

declare(strict_types=1);

namespace Tests\Feature\Http\GoogleCredential;

use App\Models\GoogleCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    #[Group('external-api')]
    public function test_coach_can_disconnect_google_calendar(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        $credential = GoogleCredential::factory()
            ->forUser($coach)
            ->create();

        // Act
        $response = $this->actingAs($coach)
            ->delete(route('settings.google-calendar.destroy'));

        // Assert
        $response
            ->assertRedirectToRoute('settings.availability.index')
            ->assertSessionHas(
                'success',
                'Google Calendarの連携を解除しました。'
            );

        $this->assertDatabaseMissing('google_credentials', [
            'id' => $credential->id,
        ]);
    }

    public function test_non_coach_cannot_disconnect_google_calendar(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act & Assert
        $this->actingAs($student)
            ->delete(route('settings.google-calendar.destroy'))
            ->assertForbidden();
    }
}
