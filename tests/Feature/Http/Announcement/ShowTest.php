<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_announcement(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $announcement = Announcement::factory()
            ->create([
                'created_by_user_id' => $admin->id,
            ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.announcements.show', $announcement));

        // Assert
        $response
            ->assertOk()
            ->assertViewIs('announcement.management.show')
            ->assertViewHas(
                'announcement',
                fn (Announcement $viewAnnouncement) => $viewAnnouncement->is(
                    $announcement
                )
            );
    }

    public function test_show_loads_required_relations(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $targetUser = User::factory()
            ->student()
            ->create();

        $announcement = Announcement::factory()
            ->forCertification($certification)
            ->create([
                'created_by_user_id' => $admin->id,
            ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.announcements.show', $announcement));

        // Assert
        $viewAnnouncement = $response->viewData('announcement');

        $this->assertTrue(
            $viewAnnouncement->relationLoaded('targetCertification')
        );

        $this->assertTrue(
            $viewAnnouncement->relationLoaded('targetUser')
        );

        $this->assertTrue(
            $viewAnnouncement->relationLoaded('createdBy')
        );
    }

    public function test_non_admin_cannot_view_announcement(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $announcement = Announcement::factory()->create();

        // Act / Assert
        $this->actingAs($coach)
            ->get(route('admin.announcements.show', $announcement))
            ->assertForbidden();
    }
}
