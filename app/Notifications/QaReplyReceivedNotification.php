<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\QaReply;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QaReplyReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly QaReply $reply,
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
     * 通知一覧画面では title / message / notification_type を使用し、
     * url は通知クリック時の遷移先として使用する。
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $thread = $this->reply->qaThread;
        $sender = $this->reply->user;

        return [
            'notification_type' => 'qa_reply_received',
            'title' => "{$sender->name}さんから質問に回答がありました。",
            'message' => null,
            'body_preview' => $this->reply->body,
            'url' => route('qa-board.show', $thread),
        ];
    }

    /**
     * メール通知。
     */
    public function toMail(object $notifiable): MailMessage
    {
        $sender = $this->reply->user;

        return (new MailMessage)
            ->subject('Certify LMS 質問掲示板の回答のご案内')
            ->greeting('Certify LMS をご利用の皆様へ')
            ->line("{$sender->name}さんから質問に回答がありました。")
            ->line($this->reply->body)
            ->action(
                '質問を確認する',
                route('qa-board.show', $this->reply->qaThread),
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
