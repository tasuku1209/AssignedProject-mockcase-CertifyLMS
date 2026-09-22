<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminAnnouncementNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Announcement $announcement,
    ) {}

    /**
     * 通知に使用するチャンネル。
     *
     * - database: 通知一覧に保存
     * - mail: メール通知
     * - broadcast: リアルタイム通知
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    /**
     * データベース通知として保存するデータ。
     *
     * 通知一覧では title / message / notification_type を使用し、
     * 通知詳細画面では body を使用する。
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'notification_type' => 'admin_announcement',
            'title' => $this->announcement->title,
            'message' => 'Certify LMS 運営チームからのお知らせ',
            'body_preview' => null,
            'body' => $this->announcement->body,
        ];
    }

    /**
     * メール通知。
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Certify LMS 運営チームからのお知らせ')
            ->greeting('Certify LMS をご利用の皆様へ')
            ->line($this->announcement->title)
            ->line($this->announcement->body)
            ->action(
                '通知一覧を確認する',
                route('notifications.index'),
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
