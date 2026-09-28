<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Password;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_password(): void
    {
        $user = User::factory()->student()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)
            ->put(route('settings.password.update'), [
                'current_password' => 'old-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $response->assertSessionHas('success', 'パスワードを変更しました。');

        $user->refresh();

        $this->assertTrue(
            Hash::check('new-password-123', $user->password)
        );

        $this->assertFalse(
            Hash::check('old-password', $user->password)
        );
    }

    public function test_password_is_not_updated_when_current_password_is_incorrect(): void
    {
        $user = User::factory()->student()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)
            ->put(route('settings.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->assertTrue(
            Hash::check('old-password', $user->fresh()->password)
        );
    }

    public function test_password_is_not_updated_when_confirmation_does_not_match(): void
    {
        $user = User::factory()->student()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)
            ->put(route('settings.password.update'), [
                'current_password' => 'old-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'different-password',
            ]);

        $response->assertSessionHasErrorsIn('updatePassword', 'password');

        $this->assertTrue(
            Hash::check('old-password', $user->fresh()->password)
        );
    }

    public function test_password_is_not_updated_when_new_password_is_too_short(): void
    {
        $user = User::factory()->student()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)
            ->put(route('settings.password.update'), [
                'current_password' => 'old-password',
                'password' => 'short',
                'password_confirmation' => 'short',
            ]);

        $response->assertSessionHasErrorsIn('updatePassword', 'password');

        $this->assertTrue(
            Hash::check('old-password', $user->fresh()->password)
        );
    }

    public function test_unauthenticated_user_cannot_update_password(): void
    {
        $response = $this->put(route('settings.password.update'), [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_graduated_student_can_update_password(): void
    {
        $user = User::factory()
            ->student()
            ->graduated()
            ->create([
                'password' => Hash::make('old-password'),
            ]);

        $response = $this->actingAs($user)
            ->put(route('settings.password.update'), [
                'current_password' => 'old-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response->assertRedirect(route('settings.profile.edit'));

        $this->assertTrue(
            Hash::check('new-password-123', $user->fresh()->password)
        );
    }
}
