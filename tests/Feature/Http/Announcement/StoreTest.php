<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_announcement(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $student = User::factory()
            ->student()
            ->create();

        // Act
        $response = $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => 'システムメンテナンスのお知らせ',
                'body' => '明日午前2時からシステムメンテナンスを実施します。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ]);

        // Assert
        $announcement = Announcement::query()->firstOrFail();

        $response
            ->assertRedirect(
                route('admin.announcements.show', $announcement)
            )
            ->assertSessionHas(
                'success',
                'お知らせを配信しました。'
            );

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => 'システムメンテナンスのお知らせ',
            'body' => '明日午前2時からシステムメンテナンスを実施します。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'target_certification_id' => null,
            'target_user_id' => null,
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_non_admin_cannot_store_announcement(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        // Act / Assert
        $this->actingAs($coach)
            ->postJson(route('admin.announcements.store'), [
                'title' => 'テスト',
                'body' => 'テスト本文',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ])
            ->assertForbidden();
    }

    public function test_store_fails_when_required_fields_are_missing(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act / Assert
        $this->actingAs($admin)
            ->postJson(route('admin.announcements.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title',
                'body',
                'target_type',
            ]);
    }

    public function test_store_fails_when_certification_target_has_no_certification_id(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act / Assert
        $this->actingAs($admin)
            ->postJson(route('admin.announcements.store'), [
                'title' => '資格別お知らせ',
                'body' => '資格別のお知らせ本文です。',
                'target_type' => AnnouncementTargetType::Certification->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'target_certification_id'
            );
    }

    public function test_store_fails_when_user_target_has_no_user_id(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act / Assert
        $this->actingAs($admin)
            ->postJson(route('admin.announcements.store'), [
                'title' => '個別お知らせ',
                'body' => '個別のお知らせ本文です。',
                'target_type' => AnnouncementTargetType::User->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'target_user_id'
            );
    }
}
