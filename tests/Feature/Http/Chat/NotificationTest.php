<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Chat;

use App\Models\Certification;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_message_notifies_all_other_chat_members(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach1 = User::factory()->coach()->inProgress()->create();
        $coach2 = User::factory()->coach()->inProgress()->create();
        $admin = User::factory()->admin()->inProgress()->create();

        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach([
            $coach1->id => [
                'id' => (string) Str::ulid(),
                'assigned_by_user_id' => $admin->id,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            $coach2->id => [
                'id' => (string) Str::ulid(),
                'assigned_by_user_id' => $admin->id,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->create();

        $room = ChatRoom::factory()
            ->for($enrollment)
            ->create();

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $student->id,
        ]);

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $coach1->id,
        ]);

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $coach2->id,
        ]);

        // Act
        $response = $this->actingAs($student)
            ->post(route('chat.storeMessage', $room), [
                'body' => '3人で相談したい内容があります。',
            ]);

        // Assert
        $response->assertRedirect(route('chat.show', $room));

        Notification::assertSentTo(
            $coach1,
            ChatMessageReceivedNotification::class,
        );

        Notification::assertSentTo(
            $coach2,
            ChatMessageReceivedNotification::class,
        );

        Notification::assertNotSentTo(
            $student,
            ChatMessageReceivedNotification::class,
        );
    }

    public function test_coach_message_notifies_other_members_except_sender(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach1 = User::factory()->coach()->inProgress()->create();
        $coach2 = User::factory()->coach()->inProgress()->create();
        $admin = User::factory()->admin()->inProgress()->create();

        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach([
            $coach1->id => [
                'id' => (string) Str::ulid(),
                'assigned_by_user_id' => $admin->id,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            $coach2->id => [
                'id' => (string) Str::ulid(),
                'assigned_by_user_id' => $admin->id,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->create();

        $room = ChatRoom::factory()
            ->for($enrollment)
            ->create();

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $student->id,
        ]);

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $coach1->id,
        ]);

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $coach2->id,
        ]);

        // Act
        $response = $this->actingAs($coach1)
            ->post(route('chat.storeMessage', $room), [
                'body' => '受講生への連絡事項です。',
            ]);

        // Assert
        $response->assertRedirect(route('chat.show', $room));

        Notification::assertSentTo(
            $student,
            ChatMessageReceivedNotification::class,
        );

        Notification::assertSentTo(
            $coach2,
            ChatMessageReceivedNotification::class,
        );

        Notification::assertNotSentTo(
            $coach1,
            ChatMessageReceivedNotification::class,
        );
    }

    public function test_message_does_not_notify_graduated_student(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()->student()->graduated()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $admin = User::factory()->admin()->inProgress()->create();

        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->create();

        $room = ChatRoom::factory()
            ->for($enrollment)
            ->create();

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $student->id,
        ]);

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $coach->id,
        ]);

        // Act
        $response = $this->actingAs($coach)
            ->post(route('chat.storeMessage', $room), [
                'body' => '卒業済み受講生へのメッセージです。',
            ]);

        // Assert
        $response->assertRedirect(route('chat.show', $room));

        Notification::assertNotSentTo(
            $student,
            ChatMessageReceivedNotification::class,
        );
    }

    public function test_message_does_not_notify_withdrawn_coach(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->withdrawn()->create();
        $admin = User::factory()->admin()->inProgress()->create();

        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->create();

        $room = ChatRoom::factory()
            ->for($enrollment)
            ->create();

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $student->id,
        ]);

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $coach->id,
        ]);

        // Act
        $response = $this->actingAs($student)
            ->post(route('chat.storeMessage', $room), [
                'body' => '退会済みコーチへのメッセージです。',
            ]);

        // Assert
        $response->assertRedirect(route('chat.show', $room));

        Notification::assertNotSentTo(
            $coach,
            ChatMessageReceivedNotification::class,
        );
    }

    public function test_chat_message_notification_is_queued_after_transaction_commits(): void
    {
        // Arrange
        Queue::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $admin = User::factory()->admin()->inProgress()->create();

        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->create();

        $room = ChatRoom::factory()
            ->for($enrollment)
            ->create();

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $student->id,
        ]);

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $coach->id,
        ]);

        // Act
        $response = $this->actingAs($student)
            ->post(route('chat.storeMessage', $room), [
                'body' => 'キュー送信のテストです。',
            ]);

        // Assert
        $response->assertRedirect(route('chat.show', $room));

        Queue::assertPushed(
            SendQueuedNotifications::class,
            function (SendQueuedNotifications $job): bool {
                return $job->notification instanceof ChatMessageReceivedNotification;
            },
        );
    }

    public function test_chat_message_notification_is_not_queued_when_transaction_rolls_back(): void
    {
        // Arrange
        config([
            'queue.default' => 'database',
            'queue.connections.database.after_commit' => true,
        ]);

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $room = ChatRoom::factory()
            ->create();

        $message = ChatMessage::factory()->make([
            'chat_room_id' => $room->id,
            'sender_user_id' => $student->id,
            'body' => 'ロールバックのテストです。',
        ]);

        // Act
        DB::beginTransaction();

        $message->save();

        $coach->notify(
            new ChatMessageReceivedNotification($message)
        );

        DB::rollBack();

        // Assert
        $this->assertDatabaseMissing('chat_messages', [
            'id' => $message->id,
        ]);

        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_failed_job_is_recorded_in_failed_jobs(): void
    {
        // Arrange
        config([
            'queue.default' => 'database',
            'queue.connections.database.after_commit' => true,
        ]);

        // Act
        DB::beginTransaction();

        dispatch(new FailingTestJob);

        DB::commit();

        Artisan::call('queue:work', [
            'connection' => 'database',
            '--once' => true,
            '--tries' => 1,
            '--backoff' => 0,
        ]);

        // Assert
        $this->assertDatabaseHas('failed_jobs', [
            'queue' => 'default',
        ]);
    }
}

final class FailingTestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public function handle(): void
    {
        throw new RuntimeException('テスト用のジョブ失敗');
    }
}
