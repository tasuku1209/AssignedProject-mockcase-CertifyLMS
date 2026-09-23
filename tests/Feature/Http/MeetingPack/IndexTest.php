<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_meeting_pack_index(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()->create();

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index'));

        // Assert
        $response
            ->assertOk()
            ->assertSee($meetingPack->name);
    }

    public function test_admin_can_search_meeting_packs_by_keyword(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $target = MeetingPack::factory()->create([
            'name' => 'スタンダード面談パック',
        ]);

        MeetingPack::factory()->create([
            'name' => 'プレミアム面談パック',
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index', [
                'keyword' => 'スタンダード',
            ]));

        // Assert
        $response
            ->assertOk()
            ->assertSee($target->name)
            ->assertDontSee('プレミアム面談パック');
    }

    public function test_admin_can_filter_meeting_packs_by_status(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $published = MeetingPack::factory()->published()->create([
            'name' => '公開中パック',
        ]);

        MeetingPack::factory()->draft()->create([
            'name' => '下書きパック',
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index', [
                'status' => 'published',
            ]));

        // Assert
        $response
            ->assertOk()
            ->assertSee($published->name)
            ->assertDontSee('下書きパック');
    }

    public function test_meeting_pack_index_is_paginated(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        MeetingPack::factory()->count(21)->create();

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index'));

        // Assert
        $response->assertOk();

        $plans = $response->viewData('plans');

        $this->assertCount(20, $plans->items());
        $this->assertSame(21, $plans->total());
    }

    public function test_meeting_pack_index_is_ordered_by_sort_order_then_created_at_desc(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $sortOrderOne = MeetingPack::factory()->create([
            'name' => '並び順1',
            'sort_order' => 1,
            'created_at' => now()->subMinutes(3),
        ]);

        $older = MeetingPack::factory()->create([
            'name' => '同順位・古い',
            'sort_order' => 2,
            'created_at' => now()->subMinutes(2),
        ]);

        $newer = MeetingPack::factory()->create([
            'name' => '同順位・新しい',
            'sort_order' => 2,
            'created_at' => now()->subMinute(),
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index'));

        // Assert
        $plans = collect($response->viewData('plans')->items());

        $this->assertSame(
            [
                $sortOrderOne->id,
                $newer->id,
                $older->id,
            ],
            $plans->pluck('id')->all()
        );
    }

    public function test_student_cannot_access_admin_meeting_packs_index(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act / Assert
        $this->actingAs($student)
            ->get(route('admin.meeting-packs.index'))
            ->assertForbidden();
    }
}
