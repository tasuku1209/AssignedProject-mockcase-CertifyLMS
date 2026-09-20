<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_get_own_notifications(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $this->createNotification($student, false);
        $this->createNotification($student, true);

        // Act
        $response = $this->actingAs($student)
            ->getJson(route('api.v1.notifications.index'));

        // Assert
        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('unread_count', 1)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'preview',
                        'url',
                        'is_read',
                        'created_at',
                    ],
                ],
                'unread_count',
            ]);
    }

    public function test_student_can_get_unread_notifications_only(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $this->createNotification($student, false);
        $this->createNotification($student, false);
        $this->createNotification($student, true);

        // Act
        $response = $this->actingAs($student)
            ->getJson(route('api.v1.notifications.index', [
                'tab' => 'unread',
            ]));

        // Assert
        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('unread_count', 2);

        $this->assertTrue(
            collect($response->json('data'))
                ->every(fn (array $notification): bool => $notification['is_read'] === false),
        );
    }

    public function test_notification_index_returns_latest_10_notifications(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        for ($i = 1; $i <= 12; $i++) {
            $createdAt = now()->subMinutes(12 - $i);

            DatabaseNotification::create([
                'id' => (string) Str::uuid(),
                'type' => 'test-notification',
                'notifiable_type' => User::class,
                'notifiable_id' => $student->id,
                'data' => [
                    'notification_type' => 'test',
                    'title' => "通知{$i}",
                    'message' => null,
                    'url' => route('notifications.index'),
                ],
                'read_at' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        // Act
        $response = $this->actingAs($student)
            ->getJson(route('api.v1.notifications.index'));

        // Assert
        $response->assertOk()
            ->assertJsonCount(10, 'data');

        $titles = collect($response->json('data'))
            ->pluck('title')
            ->all();

        $this->assertSame(
            [
                '通知12',
                '通知11',
                '通知10',
                '通知9',
                '通知8',
                '通知7',
                '通知6',
                '通知5',
                '通知4',
                '通知3',
            ],
            $titles,
        );
    }

    public function test_notification_index_does_not_show_other_users_notifications(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $this->createNotification(
            $student,
            false,
            ['title' => '自分の通知'],
        );

        $this->createNotification(
            $otherStudent,
            false,
            ['title' => '他ユーザーの通知'],
        );

        // Act
        $response = $this->actingAs($student)
            ->getJson(route('api.v1.notifications.index'));

        // Assert
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', '自分の通知');

        $this->assertNotContains(
            '他ユーザーの通知',
            collect($response->json('data'))
                ->pluck('title')
                ->all(),
        );
    }

    public function test_invalid_tab_returns_not_found(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act
        $response = $this->actingAs($student)
            ->getJson(route('api.v1.notifications.index', [
                'tab' => 'invalid',
            ]));

        // Assert
        $response->assertNotFound()
            ->assertJson([
                'message' => '指定されたリソースが見つかりません。',
                'error_code' => 'NOT_FOUND',
                'status' => 404,
            ]);
    }

    public function test_unauthenticated_user_cannot_get_notifications(): void
    {
        // Act
        $response = $this->getJson(
            route('api.v1.notifications.index'),
        );

        // Assert
        $response->assertUnauthorized();
    }

    public function test_admin_cannot_get_notifications(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)
            ->getJson(route('api.v1.notifications.index'));

        // Assert
        $response->assertForbidden();
    }

    public function test_student_can_mark_own_notification_as_read(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $notification = $this->createNotification(
            $student,
            false,
        );

        // Act
        $response = $this->actingAs($student)
            ->postJson(
                route(
                    'api.v1.notifications.markAsRead',
                    $notification,
                ),
            );

        // Assert
        $response->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull(
            $notification->fresh()->read_at,
        );
    }

    public function test_student_cannot_mark_other_users_notification_as_read(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $notification = $this->createNotification(
            $otherStudent,
            false,
        );

        // Act
        $response = $this->actingAs($student)
            ->postJson(
                route(
                    'api.v1.notifications.markAsRead',
                    $notification,
                ),
            );

        // Assert
        $response->assertForbidden();

        $this->assertNull(
            $notification->fresh()->read_at,
        );
    }

    public function test_mark_as_read_returns_not_found_for_missing_notification(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $missingNotificationId = (string) Str::uuid();

        // Act
        $response = $this->actingAs($student)
            ->postJson(
                route(
                    'api.v1.notifications.markAsRead',
                    $missingNotificationId,
                ),
            );

        // Assert
        $response->assertNotFound()
            ->assertJson([
                'message' => '指定されたリソースが見つかりません。',
                'error_code' => 'NOT_FOUND',
                'status' => 404,
            ]);
    }

    public function test_unauthenticated_user_cannot_mark_notification_as_read(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $notification = $this->createNotification(
            $student,
            false,
        );

        // Act
        $response = $this->postJson(
            route(
                'api.v1.notifications.markAsRead',
                $notification,
            ),
        );

        // Assert
        $response->assertUnauthorized();
    }

    public function test_student_can_mark_all_own_notifications_as_read(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $this->createNotification($student, false);
        $this->createNotification($student, false);
        $this->createNotification($student, true);

        // Act
        $response = $this->actingAs($student)
            ->postJson(
                route('api.v1.notifications.markAllAsRead'),
            );

        // Assert
        $response->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(
            0,
            $student->fresh()->unreadNotifications()->count(),
        );
    }

    public function test_admin_cannot_mark_all_notifications_as_read(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)
            ->postJson(
                route('api.v1.notifications.markAllAsRead'),
            );

        // Assert
        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_mark_all_notifications_as_read(): void
    {
        // Act
        $response = $this->postJson(
            route('api.v1.notifications.markAllAsRead'),
        );

        // Assert
        $response->assertUnauthorized();
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
