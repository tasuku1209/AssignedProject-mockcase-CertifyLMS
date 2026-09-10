<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_meeting_pack_create_page(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act / Assert
        $this->actingAs($admin)
            ->get(route('admin.meeting-packs.create'))
            ->assertOk()
            ->assertViewIs('meeting-pack.management.create');
    }

    public function test_non_admin_cannot_view_meeting_pack_create_page(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act / Assert
        $this->actingAs($student)
            ->get(route('admin.meeting-packs.create'))
            ->assertForbidden();
    }

    public function test_admin_can_create_meeting_pack_as_draft(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $payload = [
            'name' => '追加面談3回パック',
            'description' => '追加で3回面談できます。',
            'meeting_count' => 3,
            'price' => 15000,
            'stripe_price_id' => null,
            'sort_order' => 1,
        ];

        // Act
        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), $payload);

        // Assert
        $meetingPack = MeetingPack::firstOrFail();

        $response
            ->assertRedirect(
                route('admin.meeting-packs.show', $meetingPack)
            )
            ->assertSessionHas('success', '面談パックを作成しました。');

        $this->assertSame('追加面談3回パック', $meetingPack->name);
        $this->assertSame('追加で3回面談できます。', $meetingPack->description);
        $this->assertSame(3, $meetingPack->meeting_count);
        $this->assertSame(15000, $meetingPack->price);
        $this->assertNull($meetingPack->stripe_price_id);
        $this->assertSame(1, $meetingPack->sort_order);

        // 新規作成時は常に draft
        $this->assertSame('draft', $meetingPack->status->value);

        // 作成者・更新者はログイン中の管理者
        $this->assertSame($admin->id, $meetingPack->created_by_user_id);
        $this->assertSame($admin->id, $meetingPack->updated_by_user_id);
    }

    public function test_status_is_not_changed_by_store_request(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), [
                'name' => '追加面談3回パック',
                'description' => null,
                'meeting_count' => 3,
                'price' => 15000,
                'stripe_price_id' => null,
                'sort_order' => 1,

                // Request の rules に存在しない値
                'status' => 'published',
            ])
            ->assertRedirect();

        // Assert
        $meetingPack = MeetingPack::firstOrFail();

        // StoreAction 側で新規作成時の status を draft に固定していることを確認
        $this->assertSame('draft', $meetingPack->status->value);
    }

    public function test_store_fails_with_validation_errors_for_invalid_payload(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act / Assert
        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), [
                'name' => '',
                'description' => str_repeat('a', 2001),
                'meeting_count' => 0,
                'price' => -1,
                'stripe_price_id' => str_repeat('a', 256),
                'sort_order' => 65536,
            ])
            ->assertSessionHasErrors([
                'name',
                'description',
                'meeting_count',
                'price',
                'stripe_price_id',
                'sort_order',
            ]);

        $this->assertDatabaseCount('meeting_packs', 0);
    }

    public function test_non_admin_cannot_create_meeting_pack(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act / Assert
        $this->actingAs($student)
            ->postJson(route('admin.meeting-packs.store'), [
                'name' => '追加面談3回パック',
                'description' => '追加で3回面談できます。',
                'meeting_count' => 3,
                'price' => 15000,
                'stripe_price_id' => null,
                'sort_order' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('meeting_packs', 0);
    }
}
