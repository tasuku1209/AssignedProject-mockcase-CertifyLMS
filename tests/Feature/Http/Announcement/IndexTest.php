<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_announcements_index(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        Announcement::factory()->count(3)->create();

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.announcements.index'));

        // Assert
        $response
            ->assertOk()
            ->assertViewIs('announcement.management.index')
            ->assertViewHas('announcements');
    }

    public function test_announcements_are_ordered_by_dispatched_at_descending(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $old = Announcement::factory()
            ->dispatched(1)
            ->create([
                'dispatched_at' => now()->subDays(2),
            ]);

        $new = Announcement::factory()
            ->dispatched(1)
            ->create([
                'dispatched_at' => now()->subDay(),
            ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.announcements.index'));

        // Assert
        $announcements = $response->viewData('announcements');

        $this->assertSame(
            [$new->id, $old->id],
            $announcements->pluck('id')->all(),
        );
    }

    public function test_announcements_are_paginated_by_twenty_per_page(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        Announcement::factory()->count(21)->create();

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.announcements.index'));

        // Assert
        $announcements = $response->viewData('announcements');

        $this->assertCount(20, $announcements->items());
        $this->assertSame(21, $announcements->total());
    }

    public function test_index_loads_required_relations(): void
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

        $userAnnouncement = Announcement::factory()
            ->forUser($targetUser)
            ->create([
                'created_by_user_id' => $admin->id,
            ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.announcements.index'));

        // Assert
        $announcements = $response->viewData('announcements');

        $certificationAnnouncement = $announcements
            ->firstWhere('id', $announcement->id);

        $targetUserAnnouncement = $announcements
            ->firstWhere('id', $userAnnouncement->id);

        $this->assertTrue(
            $certificationAnnouncement->relationLoaded(
                'targetCertification'
            )
        );

        $this->assertTrue(
            $certificationAnnouncement->relationLoaded(
                'targetUser'
            )
        );

        $this->assertTrue(
            $certificationAnnouncement->relationLoaded(
                'createdBy'
            )
        );

        $this->assertTrue(
            $targetUserAnnouncement->relationLoaded(
                'targetCertification'
            )
        );

        $this->assertTrue(
            $targetUserAnnouncement->relationLoaded(
                'targetUser'
            )
        );

        $this->assertTrue(
            $targetUserAnnouncement->relationLoaded(
                'createdBy'
            )
        );
    }

    public function test_non_admin_cannot_view_announcements_index(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        // Act / Assert
        $this->actingAs($coach)
            ->get(route('admin.announcements.index'))
            ->assertForbidden();
    }
}
