<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_own_notifications(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $this->createNotification($student, false);
        $this->createNotification($student, true);

        // Act / Assert
        $this->actingAs($student)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertViewIs('notifications.index')
            ->assertViewHas('notifications', function ($notifications): bool {
                return $notifications->total() === 2;
            })
            ->assertViewHas('unreadCount', 1)
            ->assertViewHas('tab', 'all');
    }

    public function test_notifications_are_ordered_newest_first(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();

        $older = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => QaReplyReceivedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $student->id,
            'data' => [
                'notification_type' => 'qa_reply_received',
                'title' => '古い通知',
                'message' => null,
                'body_preview' => '古い回答です。',
                'url' => '#',
            ],
            'read_at' => null,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $newer = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => QaReplyReceivedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $student->id,
            'data' => [
                'notification_type' => 'qa_reply_received',
                'title' => '新しい通知',
                'message' => null,
                'body_preview' => '新しい回答です。',
                'url' => '#',
            ],
            'read_at' => null,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        // Act
        $response = $this->actingAs($student)
            ->get(route('notifications.index'));

        // Assert
        $response->assertOk();

        $notifications = $response->viewData('notifications');

        $this->assertSame($newer->id, $notifications->first()->id);
        $this->assertSame($older->id, $notifications->last()->id);
    }

    public function test_student_can_view_unread_notifications_only(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $this->createNotification($student, false);
        $this->createNotification($student, false);
        $this->createNotification($student, true);

        // Act / Assert
        $this->actingAs($student)
            ->get(route('notifications.index', ['tab' => 'unread']))
            ->assertOk()
            ->assertViewIs('notifications.index')
            ->assertViewHas('tab', 'unread')
            ->assertViewHas('unreadCount', 2)
            ->assertViewHas('notifications', function ($notifications): bool {
                return $notifications->total() === 2
                    && $notifications->every(
                        fn (DatabaseNotification $notification): bool => $notification->read_at === null,
                    );
            });
    }

    public function test_notification_index_does_not_show_other_users_notifications(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $this->createNotification($student, false);
        $this->createNotification($otherStudent, false);

        // Act / Assert
        $this->actingAs($student)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertViewHas('notifications', function ($notifications) use ($student): bool {
                return $notifications->total() === 1
                    && $notifications->first()->notifiable_id === $student->id;
            });
    }

    public function test_invalid_tab_returns_not_found(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act / Assert
        $this->actingAs($student)
            ->get(route('notifications.index', ['tab' => 'invalid']))
            ->assertNotFound();
    }

    public function test_student_can_mark_own_notification_as_read(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $notification = $this->createNotification($student, false);

        // Act / Assert
        $this->actingAs($student)
            ->post(route('notifications.markAsRead', $notification))
            ->assertRedirect();

        $this->assertNotNull(
            $notification->fresh()->read_at,
        );
    }

    public function test_student_cannot_mark_other_users_notification_as_read(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $notification = $this->createNotification($otherStudent, false);

        // Act / Assert
        $this->actingAs($student)
            ->post(route('notifications.markAsRead', $notification))
            ->assertForbidden();

        $this->assertNull(
            $notification->fresh()->read_at,
        );
    }

    public function test_mark_as_read_redirects_to_notification_url(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $url = route('notifications.index');

        $notification = $this->createNotification(
            $student,
            false,
            ['url' => $url],
        );

        // Act / Assert
        $this->actingAs($student)
            ->post(route('notifications.markAsRead', $notification))
            ->assertRedirect($url);

        $this->assertNotNull(
            $notification->fresh()->read_at,
        );
    }

    public function test_student_can_mark_all_own_notifications_as_read(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $this->createNotification($student, false);
        $this->createNotification($student, false);
        $this->createNotification($student, true);

        // Act / Assert
        $this->actingAs($student)
            ->post(route('notifications.markAllAsRead'))
            ->assertRedirect();

        $this->assertSame(
            0,
            $student->fresh()->unreadNotifications()->count(),
        );
    }

    public function test_mark_all_as_read_does_not_change_other_users_notifications(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $this->createNotification($student, false);
        $otherNotification = $this->createNotification($otherStudent, false);

        // Act / Assert
        $this->actingAs($student)
            ->post(route('notifications.markAllAsRead'))
            ->assertRedirect();

        $this->assertSame(
            0,
            $student->fresh()->unreadNotifications()->count(),
        );

        $this->assertNull(
            $otherNotification->fresh()->read_at,
        );
    }

    /**
     * テスト用 Database Notification を作成する。
     *
     * @param array<string, mixed> $data
     */
    private function createNotification(
        User $user,
        bool $read,
        array $data = [],
    ): DatabaseNotification {
        $createdAt = now();

        return DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'test-notification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => array_merge([
                'notification_type' => 'test',
                'title' => 'テスト通知',
                'message' => null,
                'url' => route('notifications.index'),
            ], $data),
            'read_at' => $read ? $createdAt : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
