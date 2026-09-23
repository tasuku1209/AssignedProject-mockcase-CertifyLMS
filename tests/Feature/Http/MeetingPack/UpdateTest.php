<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_meeting_pack_edit_page(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()->draft()->create();

        // Act / Assert
        $this->actingAs($admin)
            ->get(route('admin.meeting-packs.edit', $meetingPack))
            ->assertOk()
            ->assertViewIs('meeting-pack.management.edit')
            ->assertViewHas('plan', function (MeetingPack $plan) use ($meetingPack): bool {
                return $plan->is($meetingPack);
            });
    }

    public function test_non_admin_cannot_view_meeting_pack_edit_page(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $meetingPack = MeetingPack::factory()->draft()->create();

        // Act / Assert
        $this->actingAs($student)
            ->get(route('admin.meeting-packs.edit', $meetingPack))
            ->assertForbidden();
    }

    public function test_admin_can_update_meeting_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $previousUpdater = User::factory()->admin()->create();

        $meetingPack = MeetingPack::factory()->draft()->create([
            'name' => '旧名称',
            'description' => '旧説明',
            'meeting_count' => 3,
            'price' => 15000,
            'stripe_price_id' => 'price_old',
            'sort_order' => 1,
            'updated_by_user_id' => $previousUpdater->id,
        ]);

        $payload = [
            'name' => '新名称',
            'description' => '更新後の説明',
            'meeting_count' => 5,
            'price' => 20000,
            'stripe_price_id' => 'price_new',
            'sort_order' => 2,
        ];

        // Act
        $response = $this->actingAs($admin)
            ->put(
                route('admin.meeting-packs.update', $meetingPack),
                $payload
            );

        // Assert
        $response
            ->assertRedirect(
                route('admin.meeting-packs.show', $meetingPack)
            )
            ->assertSessionHas('success', '面談パックを更新しました。');

        $meetingPack->refresh();

        $this->assertSame(
            $admin->id,
            $meetingPack->updated_by_user_id
        );
        $this->assertSame('新名称', $meetingPack->name);
        $this->assertSame('更新後の説明', $meetingPack->description);
        $this->assertSame(5, $meetingPack->meeting_count);
        $this->assertSame(20000, $meetingPack->price);
        $this->assertSame('price_new', $meetingPack->stripe_price_id);
        $this->assertSame(2, $meetingPack->sort_order);

        // 更新者がログインユーザーになる
        $this->assertSame(
            $admin->id,
            $meetingPack->updated_by_user_id
        );
    }

    public function test_status_cannot_be_updated_via_update_request(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $meetingPack = MeetingPack::factory()->draft()->create([
            'name' => '追加面談3回パック',
        ]);

        // Act
        $this->actingAs($admin)
            ->put(
                route('admin.meeting-packs.update', $meetingPack),
                [
                    'name' => '更新後のパック',
                    'description' => null,
                    'meeting_count' => 3,
                    'price' => 15000,
                    'stripe_price_id' => null,
                    'sort_order' => 1,

                    // UpdateRequest の rules に存在しないため、
                    // 状態遷移専用アクション以外では変更できない
                    'status' => 'published',
                ]
            )
            ->assertRedirect();

        // Assert
        $meetingPack->refresh();

        $this->assertSame(
            'draft',
            $meetingPack->status->value
        );
    }

    public function test_update_fails_with_validation_errors_for_invalid_payload(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()->draft()->create();

        // Act / Assert
        $this->actingAs($admin)
            ->put(
                route('admin.meeting-packs.update', $meetingPack),
                [
                    'name' => '',
                    'description' => str_repeat('a', 2001),
                    'meeting_count' => 0,
                    'price' => -1,
                    'stripe_price_id' => str_repeat('a', 256),
                    'sort_order' => 65536,
                ]
            )
            ->assertSessionHasErrors([
                'name',
                'description',
                'meeting_count',
                'price',
                'stripe_price_id',
                'sort_order',
            ]);

        $meetingPack->refresh();

        // バリデーション失敗時は既存データが変更されない
        $this->assertSame('draft', $meetingPack->status->value);
    }

    public function test_non_admin_cannot_update_meeting_pack(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $meetingPack = MeetingPack::factory()->draft()->create();

        // Act / Assert
        $this->actingAs($student)
            ->putJson(
                route('admin.meeting-packs.update', $meetingPack),
                [
                    'name' => '不正な更新',
                    'description' => null,
                    'meeting_count' => 5,
                    'price' => 20000,
                    'stripe_price_id' => null,
                    'sort_order' => 1,
                ]
            )
            ->assertForbidden();

        $meetingPack->refresh();

        $this->assertNotSame(
            '不正な更新',
            $meetingPack->name
        );
    }
}
