<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_profile_edit_page(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act / Assert
        $this->actingAs($student)
            ->get(route('settings.profile.edit'))
            ->assertOk()
            ->assertViewIs('settings.profile')
            ->assertViewHas('user', $student);
    }

    public function test_guest_cannot_view_profile_edit_page(): void
    {
        // Act / Assert
        $this->get(route('settings.profile.edit'))
            ->assertRedirect();
    }

    public function test_student_can_update_profile(): void
    {
        // Arrange
        $student = User::factory()->student()->create([
            'name' => '変更前',
            'bio' => '変更前の自己紹介',
        ]);

        // Act
        $this->actingAs($student)
            ->patch(route('settings.profile.update'), [
                'name' => '受講生太郎',
                'bio' => '新しい自己紹介です。',
            ])
            ->assertRedirect(route('settings.profile.edit'))
            ->assertSessionHas('success', 'プロフィールを更新しました。');

        // Assert
        $student->refresh();

        $this->assertSame('受講生太郎', $student->name);
        $this->assertSame('新しい自己紹介です。', $student->bio);
    }

    public function test_coach_can_update_meeting_url(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create([
            'name' => '変更前コーチ',
            'bio' => '変更前の自己紹介',
            'meeting_url' => null,
        ]);

        // Act
        $this->actingAs($coach)
            ->patch(route('settings.profile.update'), [
                'name' => 'コーチ太郎',
                'bio' => '新しい自己紹介です。',
                'meeting_url' => 'https://meet.google.com/example-room',
            ])
            ->assertRedirect(route('settings.profile.edit'));

        // Assert
        $coach->refresh();

        $this->assertSame('コーチ太郎', $coach->name);
        $this->assertSame('新しい自己紹介です。', $coach->bio);
        $this->assertSame(
            'https://meet.google.com/example-room',
            $coach->meeting_url
        );
    }

    public function test_student_cannot_update_meeting_url(): void
    {
        // Arrange
        $student = User::factory()->student()->create([
            'meeting_url' => null,
        ]);

        // Act
        $this->actingAs($student)
            ->patch(route('settings.profile.update'), [
                'name' => '受講生太郎',
                'bio' => '自己紹介です。',
                'meeting_url' => 'https://meet.google.com/example-room',
            ])
            ->assertRedirect(route('settings.profile.edit'));

        // Assert
        $student->refresh();

        $this->assertNull($student->meeting_url);
    }

    public function test_admin_can_update_profile(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create([
            'name' => '変更前管理者',
            'bio' => '変更前の自己紹介',
        ]);

        // Act
        $this->actingAs($admin)
            ->patch(route('settings.profile.update'), [
                'name' => '管理者太郎',
                'bio' => '管理者の自己紹介です。',
            ])
            ->assertRedirect(route('settings.profile.edit'));

        // Assert
        $admin->refresh();

        $this->assertSame('管理者太郎', $admin->name);
        $this->assertSame('管理者の自己紹介です。', $admin->bio);
    }

    public function test_graduated_student_can_update_profile(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->graduated()
            ->create([
                'name' => '修了前の名前',
                'bio' => '修了前の自己紹介',
            ]);

        // Act
        $this->actingAs($student)
            ->patch(route('settings.profile.update'), [
                'name' => '修了生太郎',
                'bio' => '修了後の自己紹介です。',
            ])
            ->assertRedirect(route('settings.profile.edit'));

        // Assert
        $student->refresh();

        $this->assertSame('修了生太郎', $student->name);
        $this->assertSame('修了後の自己紹介です。', $student->bio);
    }
}
