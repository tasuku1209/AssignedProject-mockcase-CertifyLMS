<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Meeting $meeting,
        private readonly string $window,
    ) {}

    /**
     * 通知を送信するチャンネル。
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Database通知データ。
     */
    public function toArray(object $notifiable): array
    {
        $title = match ($this->window) {
            'eve' => '明日の面談のリマインドです',
            'one_hour_before' => '面談のリマインドです',
            default => '面談のリマインドです',
        };

        return [
            'notification_type' => 'meeting_reminder',
            'reminder_window' => $this->window,
            'meeting_id' => $this->meeting->id,
            'title' => $title,
            'message' => '面談日時：'.$this->meeting->scheduled_at->format('Y年n月j日 H:i'),
            'url' => route('meetings.show', $this->meeting),
        ];
    }

    /**
     * メール通知。
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = match ($this->window) {
            'eve' => '明日の面談予定をお知らせします。',
            'one_hour_before' => 'まもなく面談が開始されます。',
            default => '面談の予定をお知らせします。',
        };

        return (new MailMessage)
            ->subject('Certify LMS 面談リマインダー')
            ->greeting('Certify LMS をご利用の皆様へ')
            ->line($message)
            ->line('面談日時：'.$this->meeting->scheduled_at->format('Y年n月j日 H:i'))
            ->action(
                '面談を確認する',
                route('meetings.show', $this->meeting),
            )
            ->salutation('Certify LMS 運営チーム');
    }
}
