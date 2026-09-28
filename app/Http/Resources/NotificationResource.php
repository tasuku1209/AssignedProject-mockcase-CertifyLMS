<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Notification のポップオーバー表示用 Resource。
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->data['title'] ?? '',
            'preview' => $this->data['message']
                ?? $this->data['body_preview']
                ?? '',
            'url' => $this->data['url']
                ?? route('notifications.show', $this),
            'is_read' => $this->read_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
