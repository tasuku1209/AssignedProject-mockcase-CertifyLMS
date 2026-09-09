<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class ChatMessageReceivedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_database_mail_and_broadcast(): void
    {
        // Arrange
        $notification = new ChatMessageReceivedNotification(
            ChatMessage::factory()->create(),
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

        $room = ChatRoom::factory()->create();

        $message = ChatMessage::factory()->create([
            'chat_room_id' => $room->id,
            'sender_user_id' => $coach->id,
            'body' => '面談について確認しました。',
        ]);

        $notification = new ChatMessageReceivedNotification($message);

        // Act
        $data = $notification->toArray($student);

        // Assert
        $this->assertSame('chat_message_received', $data['notification_type']);
        $this->assertSame(
            'コーチ太郎さんからメッセージが届きました。',
            $data['title'],
        );
        $this->assertNull($data['message']);
        $this->assertSame(
            '面談について確認しました。',
            $data['body_preview'],
        );
        $this->assertSame(
            route('chat.show', $room),
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

        $room = ChatRoom::factory()->create();

        $message = ChatMessage::factory()->create([
            'chat_room_id' => $room->id,
            'sender_user_id' => $coach->id,
            'body' => '面談について確認しました。',
        ]);

        $notification = new ChatMessageReceivedNotification($message);

        // Act
        $mail = $notification->toMail($student);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame(
            'Certify LMS チャット受信のご案内',
            $mail->subject,
        );

        $this->assertSame(
            'Certify LMS をご利用の皆様へ',
            $mail->greeting,
        );

        $this->assertStringContainsString(
            'コーチ太郎さんからメッセージが届きました。',
            $this->mailLines($mail),
        );

        $this->assertStringContainsString(
            '面談について確認しました。',
            $this->mailLines($mail),
        );

        $this->assertSame(
            'チャットを確認する',
            $mail->actionText,
        );

        $this->assertSame(
            route('chat.show', $room),
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

        $room = ChatRoom::factory()->create();

        $message = ChatMessage::factory()->create([
            'chat_room_id' => $room->id,
            'sender_user_id' => $coach->id,
        ]);

        $notification = new ChatMessageReceivedNotification($message);

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
