<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class MeetingReminderNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_database_and_mail(): void
    {
        // Arrange
        $meeting = Meeting::factory()->create();

        $notification = new MeetingReminderNotification(
            $meeting,
            'eve',
        );

        // Act
        $channels = $notification->via(
            User::factory()->student()->create(),
        );

        // Assert
        $this->assertSame(
            ['database', 'mail'],
            $channels,
        );
    }

    public function test_to_array_returns_expected_data_for_eve(): void
    {
        // Arrange
        $meeting = Meeting::factory()->create();

        $notification = new MeetingReminderNotification(
            $meeting,
            'eve',
        );

        // Act
        $data = $notification->toArray($meeting->student);

        // Assert
        $this->assertSame(
            'meeting_reminder',
            $data['notification_type'],
        );

        $this->assertSame(
            '明日の面談のリマインドです',
            $data['title'],
        );

        $this->assertSame(
            $meeting->id,
            $data['meeting_id'],
        );

        $this->assertSame(
            'eve',
            $data['reminder_window'],
        );

        $this->assertSame(
            route('meetings.show', $meeting),
            $data['url'],
        );
    }

    public function test_to_array_returns_expected_data_for_one_hour_before(): void
    {
        // Arrange
        $meeting = Meeting::factory()->create();

        $notification = new MeetingReminderNotification(
            $meeting,
            'one_hour_before',
        );

        // Act
        $data = $notification->toArray($meeting->student);

        // Assert
        $this->assertSame(
            'meeting_reminder',
            $data['notification_type'],
        );

        $this->assertSame(
            '面談のリマインドです',
            $data['title'],
        );

        $this->assertSame(
            $meeting->id,
            $data['meeting_id'],
        );

        $this->assertSame(
            'one_hour_before',
            $data['reminder_window'],
        );

        $this->assertSame(
            route('meetings.show', $meeting),
            $data['url'],
        );
    }

    public function test_to_mail_returns_expected_subject_and_content_for_eve(): void
    {
        // Arrange
        $meeting = Meeting::factory()->create();

        $notification = new MeetingReminderNotification(
            $meeting,
            'eve',
        );

        // Act
        $mail = $notification->toMail($meeting->student);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);

        $this->assertSame(
            'Certify LMS 面談リマインダー',
            $mail->subject,
        );

        $this->assertSame(
            'Certify LMS をご利用の皆様へ',
            $mail->greeting,
        );

        $this->assertStringContainsString(
            '明日の面談予定をお知らせします。',
            $this->mailLines($mail),
        );

        $this->assertSame(
            '面談を確認する',
            $mail->actionText,
        );

        $this->assertSame(
            route('meetings.show', $meeting),
            $mail->actionUrl,
        );

        $this->assertSame(
            'Certify LMS 運営チーム',
            $mail->salutation,
        );
    }

    public function test_to_mail_returns_expected_subject_and_content_for_one_hour_before(): void
    {
        // Arrange
        $meeting = Meeting::factory()->create();

        $notification = new MeetingReminderNotification(
            $meeting,
            'one_hour_before',
        );

        // Act
        $mail = $notification->toMail($meeting->student);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);

        $this->assertSame(
            'Certify LMS 面談リマインダー',
            $mail->subject,
        );

        $this->assertSame(
            'Certify LMS をご利用の皆様へ',
            $mail->greeting,
        );

        $this->assertStringContainsString(
            'まもなく面談が開始されます。',
            $this->mailLines($mail),
        );

        $this->assertSame(
            '面談を確認する',
            $mail->actionText,
        );

        $this->assertSame(
            route('meetings.show', $meeting),
            $mail->actionUrl,
        );

        $this->assertSame(
            'Certify LMS 運営チーム',
            $mail->salutation,
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
