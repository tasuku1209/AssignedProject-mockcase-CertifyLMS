<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Avatar;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_upload_avatar(): void
    {
        // Arrange
        Storage::fake('public');

        $student = User::factory()->student()->create([
            'avatar_url' => null,
        ]);

        $file = UploadedFile::fake()->image('avatar.png', 200, 200);

        // Act
        $this->actingAs($student)
            ->post(route('settings.avatar.store'), [
                'avatar' => $file,
            ])
            ->assertRedirect(route('settings.profile.edit'))
            ->assertSessionHas(
                'success',
                'アバター画像を更新しました。'
            );

        // Assert
        $student->refresh();

        $this->assertNotNull($student->avatar_url);

        $path = ltrim(
            str_replace('/storage/', '', $student->avatar_url),
            '/'
        );

        Storage::disk('public')->assertExists($path);
    }

    public function test_authenticated_user_can_replace_existing_avatar(): void
    {
        // Arrange
        Storage::fake('public');

        $student = User::factory()->student()->create();

        Storage::disk('public')->put(
            'avatars/old-avatar.png',
            'old avatar'
        );

        $student->update([
            'avatar_url' => Storage::disk('public')
                ->url('avatars/old-avatar.png'),
        ]);

        $file = UploadedFile::fake()->image('new-avatar.png', 200, 200);

        // Act
        $this->actingAs($student)
            ->post(route('settings.avatar.store'), [
                'avatar' => $file,
            ])
            ->assertRedirect(route('settings.profile.edit'));

        // Assert
        $student->refresh();

        $this->assertNotNull($student->avatar_url);

        $newPath = ltrim(
            str_replace('/storage/', '', $student->avatar_url),
            '/'
        );

        Storage::disk('public')->assertExists($newPath);
        $this->assertNotSame(
            Storage::disk('public')->url('avatars/old-avatar.png'),
            $student->avatar_url
        );
    }

    public function test_authenticated_user_can_delete_avatar(): void
    {
        // Arrange
        Storage::fake('public');

        $student = User::factory()->student()->create();

        Storage::disk('public')->put(
            'avatars/avatar.png',
            'avatar'
        );

        $student->update([
            'avatar_url' => Storage::disk('public')
                ->url('avatars/avatar.png'),
        ]);

        // Act
        $this->actingAs($student)
            ->delete(route('settings.avatar.destroy'))
            ->assertRedirect(route('settings.profile.edit'))
            ->assertSessionHas(
                'success',
                'アイコン画像を削除しました。'
            );

        // Assert
        $student->refresh();

        $this->assertNull($student->avatar_url);
    }

    public function test_guest_cannot_upload_avatar(): void
    {
        // Arrange
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.png', 200, 200);

        // Act / Assert
        $this->post(route('settings.avatar.store'), [
            'avatar' => $file,
        ])->assertRedirect();
    }

    public function test_guest_cannot_delete_avatar(): void
    {
        // Act / Assert
        $this->delete(route('settings.avatar.destroy'))
            ->assertRedirect();
    }

    public function test_upload_rejects_invalid_avatar_file(): void
    {
        // Arrange
        Storage::fake('public');

        $student = User::factory()->student()->create();

        $file = UploadedFile::fake()->create(
            'avatar.gif',
            100,
            'image/gif'
        );

        // Act / Assert
        $this->actingAs($student)
            ->post(route('settings.avatar.store'), [
                'avatar' => $file,
            ])
            ->assertSessionHasErrors('avatar');
    }

    public function test_upload_rejects_oversized_avatar_file(): void
    {
        // Arrange
        Storage::fake('public');

        $student = User::factory()->student()->create();

        $file = UploadedFile::fake()->create(
            'avatar.png',
            2049,
            'image/png'
        );

        // Act / Assert
        $this->actingAs($student)
            ->post(route('settings.avatar.store'), [
                'avatar' => $file,
            ])
            ->assertSessionHasErrors('avatar');
    }
}
