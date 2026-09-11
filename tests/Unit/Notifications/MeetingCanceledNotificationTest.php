<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingCanceledNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class MeetingCanceledNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_database_mail_and_broadcast(): void
    {
        // Arrange
        $notification = new MeetingCanceledNotification(
            Meeting::factory()->create(),
        );

        // Act
        $channels = $notification->via(
            User::factory()->student()->create(),
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
        $student = User::factory()->student()->create();

        $coach = User::factory()->coach()->create([
            'name' => 'コーチ太郎',
        ]);

        $meeting = Meeting::factory()
            ->forStudent($student)
            ->forCoach($coach)
            ->create([
                'scheduled_at' => now()->addDays(3)->setHour(14)->setMinute(0)->setSecond(0),
                'canceled_by_user_id' => $coach->id,
            ]);

        $notification = new MeetingCanceledNotification($meeting);

        // Act
        $data = $notification->toArray($student);

        // Assert
        $this->assertSame('meeting_canceled', $data['notification_type']);
        $this->assertSame(
            'コーチ太郎さんが面談をキャンセルしました。',
            $data['title'],
        );
        $this->assertSame(
            '面談日時：'.$meeting->scheduled_at->format('Y年n月j日 H:i'),
            $data['message'],
        );
        $this->assertSame(
            route('meetings.show', $meeting),
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

        $meeting = Meeting::factory()
            ->forStudent($student)
            ->forCoach($coach)
            ->create([
                'scheduled_at' => now()->addDays(3)->setHour(14)->setMinute(0)->setSecond(0),
                'canceled_by_user_id' => $coach->id,
            ]);

        $notification = new MeetingCanceledNotification($meeting);

        // Act
        $mail = $notification->toMail($student);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame(
            'Certify LMS 面談キャンセルのご案内',
            $mail->subject,
        );

        $this->assertSame(
            'Certify LMS をご利用の皆様へ',
            $mail->greeting,
        );

        $this->assertStringContainsString(
            'コーチ太郎さんが面談をキャンセルしました。',
            $this->mailLines($mail),
        );

        $this->assertStringContainsString(
            '面談日時：'.$meeting->scheduled_at->format('Y年n月j日 H:i'),
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
            ->create([
                'canceled_by_user_id' => $coach->id,
            ]);

        $notification = new MeetingCanceledNotification($meeting);

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
