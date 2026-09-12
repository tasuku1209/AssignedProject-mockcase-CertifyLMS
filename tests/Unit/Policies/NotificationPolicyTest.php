<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\NotificationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_any_allowed_for_student(): void
    {
        $student = User::factory()->student()->create();

        $this->assertTrue(
            app(NotificationPolicy::class)->viewAny($student)
        );
    }

    public function test_view_any_allowed_for_coach(): void
    {
        $coach = User::factory()->coach()->create();

        $this->assertTrue(
            app(NotificationPolicy::class)->viewAny($coach)
        );
    }

    public function test_view_any_denied_for_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertFalse(
            app(NotificationPolicy::class)->viewAny($admin)
        );
    }

    public function test_mark_as_read_allowed_for_own_notification(): void
    {
        $student = User::factory()->student()->create();

        $notification = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'notifiable_type' => User::class,
            'notifiable_id' => $student->id,
            'data' => [],
        ]);

        $this->assertTrue(
            app(NotificationPolicy::class)->markAsRead($student, $notification)
        );
    }

    public function test_mark_as_read_denied_for_other_users_notification(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $notification = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'notifiable_type' => User::class,
            'notifiable_id' => $otherStudent->id,
            'data' => [],
        ]);

        $this->assertFalse(
            app(NotificationPolicy::class)->markAsRead($student, $notification)
        );
    }

    public function test_mark_as_read_denied_for_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $notification = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'notifiable_type' => User::class,
            'notifiable_id' => $admin->id,
            'data' => [],
        ]);

        $this->assertFalse(
            app(NotificationPolicy::class)->markAsRead($admin, $notification)
        );
    }

    public function test_mark_all_as_read_allowed_for_student(): void
    {
        $student = User::factory()->student()->create();

        $this->assertTrue(
            app(NotificationPolicy::class)->markAllAsRead($student)
        );
    }

    public function test_mark_all_as_read_allowed_for_coach(): void
    {
        $coach = User::factory()->coach()->create();

        $this->assertTrue(
            app(NotificationPolicy::class)->markAllAsRead($coach)
        );
    }

    public function test_mark_all_as_read_denied_for_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertFalse(
            app(NotificationPolicy::class)->markAllAsRead($admin)
        );
    }
}
