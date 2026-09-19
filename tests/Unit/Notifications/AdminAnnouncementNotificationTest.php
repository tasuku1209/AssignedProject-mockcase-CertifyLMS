<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

/**
 * 運営お知らせ通知のチャンネル・Database・Mail・Broadcast
 * 通知内容を検証する Unit テスト。
 */
class AdminAnnouncementNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_database_mail_and_broadcast(): void
    {
        // Arrange
        $notification = new AdminAnnouncementNotification(
            Announcement::factory()->create(),
        );

        $student = User::factory()
            ->student()
            ->create();

        // Act
        $channels = $notification->via($student);

        // Assert
        $this->assertSame(
            ['database', 'mail', 'broadcast'],
            $channels,
        );
    }

    public function test_to_array_returns_expected_data(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->create();

        $announcement = Announcement::factory()
            ->create([
                'title' => 'システムメンテナンスのお知らせ',
                'body' => '9月10日 2:00〜4:00にシステムメンテナンスを実施します。',
            ]);

        $notification = new AdminAnnouncementNotification(
            $announcement,
        );

        // Act
        $data = $notification->toArray($student);

        // Assert
        $this->assertSame(
            'admin_announcement',
            $data['notification_type'],
        );

        $this->assertSame(
            'システムメンテナンスのお知らせ',
            $data['title'],
        );

        $this->assertSame(
            'Certify LMS 運営チームからのお知らせ',
            $data['message'],
        );

        $this->assertNull(
            $data['body_preview'],
        );

        $this->assertSame(
            '9月10日 2:00〜4:00にシステムメンテナンスを実施します。',
            $data['body'],
        );
    }

    public function test_to_mail_returns_expected_subject_and_content(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->create();

        $announcement = Announcement::factory()
            ->create([
                'title' => 'システムメンテナンスのお知らせ',
                'body' => '9月10日 2:00〜4:00にシステムメンテナンスを実施します。',
            ]);

        $notification = new AdminAnnouncementNotification(
            $announcement,
        );

        // Act
        $mail = $notification->toMail($student);

        // Assert
        $this->assertInstanceOf(
            MailMessage::class,
            $mail,
        );

        $this->assertSame(
            'Certify LMS 運営チームからのお知らせ',
            $mail->subject,
        );

        $this->assertSame(
            'Certify LMS をご利用の皆様へ',
            $mail->greeting,
        );

        $this->assertStringContainsString(
            'システムメンテナンスのお知らせ',
            $this->mailLines($mail),
        );

        $this->assertStringContainsString(
            '9月10日 2:00〜4:00にシステムメンテナンスを実施します。',
            $this->mailLines($mail),
        );

        $this->assertSame(
            '通知一覧を確認する',
            $mail->actionText,
        );

        $this->assertSame(
            route('notifications.index'),
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
        $student = User::factory()
            ->student()
            ->create();

        $announcement = Announcement::factory()
            ->create();

        $notification = new AdminAnnouncementNotification(
            $announcement,
        );

        // Act
        $broadcast = $notification->toBroadcast($student);

        // Assert
        $this->assertInstanceOf(
            BroadcastMessage::class,
            $broadcast,
        );

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
