<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingReservedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Meeting $meeting,
    ) {}

    /**
     * 通知を送信するチャンネル。
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    /**
     * Database / Broadcast に渡すデータ。
     */
    public function toArray(object $notifiable): array
    {
        $student = $this->meeting->student;

        return [
            'notification_type' => 'meeting_reserved',
            'title' => "{$student->name}さんから面談予約がありました。",
            'message' => null,
            'body_preview' => $this->meeting->topic,
            'url' => route('meetings.show', $this->meeting),
        ];
    }

    /**
     * メール通知。
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Certify LMS 面談予約のご案内')
            ->greeting('Certify LMS をご利用の皆様へ')
            ->line("{$this->meeting->student->name}さんから面談予約がありました。")
            ->line($this->meeting->topic)
            ->action(
                '面談を確認する',
                route('meetings.show', $this->meeting),
            )
            ->salutation('Certify LMS 運営チーム');
    }

    /**
     * Broadcast通知。
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
