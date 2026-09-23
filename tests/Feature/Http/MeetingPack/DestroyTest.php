<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_draft_meeting_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()->draft()->create();

        // Act
        $response = $this->actingAs($admin)
            ->delete(
                route('admin.meeting-packs.destroy', $meetingPack)
            );

        // Assert
        $response
            ->assertRedirect(route('admin.meeting-packs.index'))
            ->assertSessionHas('success', '面談パックを削除しました。');

        $this->assertDatabaseMissing('meeting_packs', [
            'id' => $meetingPack->id,
        ]);
    }

    public function test_admin_can_delete_archived_meeting_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()->archived()->create();

        // Act
        $response = $this->actingAs($admin)
            ->delete(
                route('admin.meeting-packs.destroy', $meetingPack)
            );

        // Assert
        $response
            ->assertRedirect(route('admin.meeting-packs.index'))
            ->assertSessionHas('success', '面談パックを削除しました。');

        $this->assertDatabaseMissing('meeting_packs', [
            'id' => $meetingPack->id,
        ]);
    }

    public function test_admin_cannot_delete_published_meeting_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()->published()->create();

        // Act / Assert
        $this->actingAs($admin)
            ->deleteJson(
                route('admin.meeting-packs.destroy', $meetingPack)
            )
            ->assertStatus(409);

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $meetingPack->id,
        ]);
    }

    public function test_non_admin_cannot_delete_meeting_pack(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $meetingPack = MeetingPack::factory()->draft()->create();

        // Act / Assert
        $this->actingAs($student)
            ->deleteJson(
                route('admin.meeting-packs.destroy', $meetingPack)
            )
            ->assertForbidden();

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $meetingPack->id,
        ]);
    }
}
