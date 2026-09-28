<?php

declare(strict_types=1);

namespace App\UseCases\AiChatConversation;

use App\Models\AiChatConversation;

final class ShowAction
{
    /**
     * AIチャット相談詳細に必要な関連データを取得する。
     */
    public function __invoke(AiChatConversation $conversation): AiChatConversation
    {
        return $conversation->load([
            'messages',
            'enrollment.certification',
            'section',
        ]);
    }
}
