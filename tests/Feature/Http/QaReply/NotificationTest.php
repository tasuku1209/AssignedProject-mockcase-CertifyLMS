<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaReply;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaReplyReceivedNotification;
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

    public function test_coach_reply_sends_notification_to_thread_owner(): void
    {
        // Arrange
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->inProgress()->create();

        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $thread = QaThread::factory()
            ->for($certification, 'certification')
            ->forUser($student)
            ->create();

        // Act
        $response = $this->actingAs($coach)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => 'ご質問について回答します。',
            ]);

        // Assert
        $response->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $coach->id,
            'body' => 'ご質問について回答します。',
        ]);

        Notification::assertSentTo(
            $student,
            QaReplyReceivedNotification::class,
        );
    }

    public function test_thread_owner_is_not_notified_when_replying_to_own_thread(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();

        $thread = QaThread::factory()
            ->for($student, 'user')
            ->create();

        // Act
        $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => '自分の質問への追記です。',
            ])
            ->assertRedirect(route('qa-board.show', $thread));

        // Assert
        Notification::assertNotSentTo(
            $student,
            QaReplyReceivedNotification::class,
        );
    }

    public function test_reply_to_non_in_progress_student_does_not_send_notification(): void
    {
        // Arrange
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->graduated()->create();

        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $thread = QaThread::factory()
            ->for($certification, 'certification')
            ->forUser($student)
            ->create();

        // Act
        $response = $this->actingAs($coach)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => 'ご質問について回答します。',
            ]);

        // Assert
        $response->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $coach->id,
            'body' => 'ご質問について回答します。',
        ]);

        Notification::assertNotSentTo(
            $student,
            QaReplyReceivedNotification::class,
        );
    }

    public function test_qa_reply_notification_is_queued_after_transaction_commits(): void
    {
        // Arrange
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->inProgress()->create();

        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $thread = QaThread::factory()
            ->for($certification, 'certification')
            ->forUser($student)
            ->create();

        // Act
        $response = $this->actingAs($coach)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => 'キュー送信のテストです。',
            ]);

        // Assert
        $response->assertRedirect(route('qa-board.show', $thread));

        Queue::assertPushed(
            SendQueuedNotifications::class,
            function (SendQueuedNotifications $job): bool {
                return $job->notification instanceof QaReplyReceivedNotification;
            },
        );
    }

    public function test_qa_reply_notification_is_not_queued_when_transaction_rolls_back(): void
    {
        // Arrange
        config([
            'queue.default' => 'database',
            'queue.connections.database.after_commit' => true,
        ]);

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $thread = QaThread::factory()
            ->for($student, 'user')
            ->create();

        $reply = QaReply::factory()
            ->forThread($thread)
            ->forUser($coach)
            ->make([
                'body' => 'ロールバックのテストです。',
            ]);

        // Act
        DB::beginTransaction();

        $reply->save();

        $student->notify(
            new QaReplyReceivedNotification($reply)
        );

        DB::rollBack();

        // Assert
        $this->assertDatabaseMissing('qa_replies', [
            'id' => $reply->id,
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
