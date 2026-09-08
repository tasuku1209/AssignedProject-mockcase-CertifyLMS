<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChatMessageReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ChatMessage $message,
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
        $room = $this->message->chatRoom;
        $sender = $this->message->sender;

        return [
            'notification_type' => 'chat_message_received',
            'title' => "{$sender->name}さんからメッセージが届きました。",
            'message' => null,
            'body_preview' => $this->message->body,
            'url' => route('chat.show', $room),
        ];
    }

    /**
     * メール通知。
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Certify LMS チャット受信のご案内')
            ->greeting('Certify LMS をご利用の皆様へ')
            ->line("{$this->message->sender->name}さんからメッセージが届きました。")
            ->line($this->message->body)
            ->action(
                'チャットを確認する',
                route('chat.show', $this->message->chatRoom),
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
