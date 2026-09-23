<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReservedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class MeetingReservedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_database_mail_and_broadcast(): void
    {
        // Arrange
        $notification = new MeetingReservedNotification(
            Meeting::factory()->create(),
        );

        // Act
        $channels = $notification->via(
            User::factory()->coach()->create(),
        );

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

        $coach = User::factory()->coach()->create();

        $meeting = Meeting::factory()
            ->forStudent($student)
            ->forCoach($coach)
            ->create([
                'topic' => '模擬試験について相談したいです。',
            ]);

        $notification = new MeetingReservedNotification($meeting);

        // Act
        $data = $notification->toArray($coach);

        // Assert
        $this->assertSame('meeting_reserved', $data['notification_type']);
        $this->assertSame(
            '受講生花子さんから面談予約がありました。',
            $data['title'],
        );
        $this->assertNull($data['message']);
        $this->assertSame(
            '模擬試験について相談したいです。',
            $data['body_preview'],
        );
        $this->assertSame(
            route('meetings.show', $meeting),
            $data['url'],
        );
    }

    public function test_to_mail_returns_expected_subject_and_content(): void
    {
        // Arrange
        $student = User::factory()->student()->create([
            'name' => '受講生花子',
        ]);

        $coach = User::factory()->coach()->create();

        $meeting = Meeting::factory()
            ->forStudent($student)
            ->forCoach($coach)
            ->create([
                'topic' => '模擬試験について相談したいです。',
            ]);

        $notification = new MeetingReservedNotification($meeting);

        // Act
        $mail = $notification->toMail($coach);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame(
            'Certify LMS 面談予約のご案内',
            $mail->subject,
        );

        $this->assertSame(
            'Certify LMS をご利用の皆様へ',
            $mail->greeting,
        );

        $this->assertStringContainsString(
            '受講生花子さんから面談予約がありました。',
            $this->mailLines($mail),
        );

        $this->assertStringContainsString(
            '模擬試験について相談したいです。',
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

    public function test_to_broadcast_returns_expected_message(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();

        $meeting = Meeting::factory()
            ->forStudent($student)
            ->forCoach($coach)
            ->create();

        $notification = new MeetingReservedNotification($meeting);

        // Act
        $broadcast = $notification->toBroadcast($coach);

        // Assert
        $this->assertInstanceOf(BroadcastMessage::class, $broadcast);
        $this->assertSame(
            $notification->toArray($coach),
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
