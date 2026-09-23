<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class QaReplyReceivedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_database_mail_and_broadcast(): void
    {
        // Arrange
        $notification = new QaReplyReceivedNotification(
            QaReply::factory()->create(),
        );

        // Act
        $channels = $notification->via(User::factory()->student()->create());

        // Assert
        $this->assertSame(
            ['database', 'mail', 'broadcast'],
            $channels,
        );
    }

    public function test_to_array_returns_expected_data(): void
    {
        // Arrange
        $student = User::factory()->student()->create([
            'name' => '受講生花子',
        ]);

        $coach = User::factory()->coach()->create([
            'name' => 'コーチ太郎',
        ]);

        $thread = QaThread::factory()
            ->for($student)
            ->create();

        $reply = QaReply::factory()
            ->forThread($thread)
            ->forUser($coach)
            ->create([
                'body' => 'この内容については教材の第3章を確認してください。',
            ]);

        $notification = new QaReplyReceivedNotification($reply);

        // Act
        $data = $notification->toArray($student);

        // Assert
        $this->assertSame('qa_reply_received', $data['notification_type']);
        $this->assertSame(
            'コーチ太郎さんから質問に回答がありました。',
            $data['title'],
        );
        $this->assertNull($data['message']);
        $this->assertSame(
            'この内容については教材の第3章を確認してください。',
            $data['body_preview'],
        );
        $this->assertSame(
            route('qa-board.show', $thread),
            $data['url'],
        );
    }

    public function test_to_mail_returns_expected_subject_and_content(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $coach = User::factory()->coach()->create([
            'name' => 'コーチ太郎',
        ]);

        $thread = QaThread::factory()
            ->for($student)
            ->create();

        $reply = QaReply::factory()
            ->forThread($thread)
            ->forUser($coach)
            ->create([
                'body' => 'この内容については教材の第3章を確認してください。',
            ]);

        $notification = new QaReplyReceivedNotification($reply);

        // Act
        $mail = $notification->toMail($student);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame(
            'Certify LMS 質問掲示板の回答のご案内',
            $mail->subject,
        );

        $this->assertSame(
            'Certify LMS をご利用の皆様へ',
            $mail->greeting,
        );

        $this->assertStringContainsString(
            'コーチ太郎さんから質問に回答がありました。',
            $this->mailLines($mail),
        );

        $this->assertStringContainsString(
            'この内容については教材の第3章を確認してください。',
            $this->mailLines($mail),
        );

        $this->assertSame(
            '質問を確認する',
            $mail->actionText,
        );

        $this->assertSame(
            route('qa-board.show', $thread),
            $mail->actionUrl,
        );

        $this->assertSame(
            'Certify LMS 運営チーム',
            $mail->salutation,
        );
    }

    public function test_to_broadcast_returns_expected_message(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create([
            'name' => 'コーチ太郎',
        ]);

        $thread = QaThread::factory()
            ->for($student)
            ->create();

        $reply = QaReply::factory()
            ->forThread($thread)
            ->forUser($coach)
            ->create();

        $notification = new QaReplyReceivedNotification($reply);

        // Act
        $broadcast = $notification->toBroadcast($student);

        // Assert
        $this->assertInstanceOf(BroadcastMessage::class, $broadcast);
        $this->assertSame(
            $notification->toArray($student),
            $broadcast->data,
        );
    }

    /**
     * MailMessage の line 内容を文字列として取得する。
     */
    private function mailLines(MailMessage $mail): string
    {
        return collect($mail->introLines)
            ->merge($mail->outroLines)
            ->implode("\n");
    }
}
