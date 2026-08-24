<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_meeting_pack_as_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), [
                'name' => '追加面談3回パック',
                'description' => '追加で3回面談できます。',
                'meeting_count' => 3,
                'price' => 15000,
                'stripe_price_id' => null,
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $meetingPack = MeetingPack::firstOrFail();

        $this->assertSame('追加面談3回パック', $meetingPack->name);
        $this->assertSame(3, $meetingPack->meeting_count);
        $this->assertSame(15000, $meetingPack->price);

        // 新規作成時は必ず下書き
        $this->assertSame('draft', $meetingPack->status->value);

        $this->assertSame($admin->id, $meetingPack->created_by_user_id);
        $this->assertSame($admin->id, $meetingPack->updated_by_user_id);
    }

    public function test_admin_can_update_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();

        $meetingPack = MeetingPack::factory()->draft()->create([
            'name' => '旧名称',
            'meeting_count' => 3,
            'price' => 15000,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.meeting-packs.update', $meetingPack), [
                'name' => '新名称',
                'description' => '更新後の説明',
                'meeting_count' => 5,
                'price' => 20000,
                'stripe_price_id' => null,
                'sort_order' => 2,
            ])
            ->assertRedirect();

        $meetingPack->refresh();

        $this->assertSame('新名称', $meetingPack->name);
        $this->assertSame('更新後の説明', $meetingPack->description);
        $this->assertSame(5, $meetingPack->meeting_count);
        $this->assertSame(20000, $meetingPack->price);
        $this->assertSame(2, $meetingPack->sort_order);
    }

    public function test_admin_can_delete_draft_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();

        $meetingPack = MeetingPack::factory()->draft()->create();

        $this->actingAs($admin)
            ->delete(route('admin.meeting-packs.destroy', $meetingPack))
            ->assertRedirect(route('admin.meeting-packs.index'));

        $this->assertDatabaseMissing('meeting_packs', [
            'id' => $meetingPack->id,
        ]);
    }

    public function test_admin_can_delete_archived_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();

        $meetingPack = MeetingPack::factory()->archived()->create();

        $this->actingAs($admin)
            ->delete(route('admin.meeting-packs.destroy', $meetingPack))
            ->assertRedirect(route('admin.meeting-packs.index'));

        $this->assertDatabaseMissing('meeting_packs', [
            'id' => $meetingPack->id,
        ]);
    }

    public function test_admin_cannot_delete_published_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();

        $meetingPack = MeetingPack::factory()->published()->create();

        $this->actingAs($admin)
            ->deleteJson(route('admin.meeting-packs.destroy', $meetingPack))
            ->assertStatus(409);

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $meetingPack->id,
        ]);
    }

    public function test_student_cannot_access_admin_meeting_packs_index(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('admin.meeting-packs.index'))
            ->assertForbidden();
    }
}
