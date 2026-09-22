<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_meeting_pack_detail_with_creator_and_updater_loaded(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $creator = User::factory()->admin()->create();
        $updater = User::factory()->admin()->create();

        $meetingPack = MeetingPack::factory()->create([
            'name' => '追加面談3回パック',
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => $updater->id,
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.show', $meetingPack));

        // Assert
        $response
            ->assertOk()
            ->assertViewIs('meeting-pack.management.show')
            ->assertViewHas('plan', function (MeetingPack $plan) use (
                $meetingPack,
                $creator,
                $updater
            ): bool {
                return $plan->is($meetingPack)
                    && $plan->relationLoaded('createdBy')
                    && $plan->relationLoaded('updatedBy')
                    && $plan->createdBy->is($creator)
                    && $plan->updatedBy->is($updater);
            })
            ->assertSee('追加面談3回パック');
    }

    public function test_student_cannot_view_meeting_pack_detail(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $meetingPack = MeetingPack::factory()->create();

        // Act / Assert
        $this->actingAs($student)
            ->get(route('admin.meeting-packs.show', $meetingPack))
            ->assertForbidden();
    }
}
