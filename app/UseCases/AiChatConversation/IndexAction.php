<?php

declare(strict_types=1);

namespace App\UseCases\AiChatConversation;

use App\Models\AiChatConversation;
use App\Models\User;

final class IndexAction
{
    /**
     * ログインユーザーの最新のAIチャット相談を取得する。
     */
    public function __invoke(User $viewer): ?AiChatConversation
    {
        return AiChatConversation::query()
            ->where('user_id', $viewer->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->first();
    }
}
