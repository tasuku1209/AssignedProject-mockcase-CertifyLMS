<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingCanceledNotification extends Notification implements ShouldQueue
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
        $actor = $this->meeting->canceledBy;

        return [
            'notification_type' => 'meeting_canceled',
            'title' => "{$actor->name}さんが面談をキャンセルしました。",
            'message' => '面談日時：'.$this->meeting->scheduled_at->format('Y年n月j日 H:i'),
            'url' => route('meetings.show', $this->meeting),
        ];
    }

    /**
     * メール通知。
     */
    public function toMail(object $notifiable): MailMessage
    {
        $actor = $this->meeting->canceledBy;

        return (new MailMessage)
            ->subject('[CertifyLMS]面談キャンセルのお知らせ')
            ->line("{$actor->name}さんが面談をキャンセルしました。")
            ->line('面談日時：'.$this->meeting->scheduled_at->format('Y年n月j日 H:i'))
            ->action(
                '面談を確認する',
                route('meetings.show', $this->meeting),
            );
    }

    /**
     * Broadcast通知。
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
