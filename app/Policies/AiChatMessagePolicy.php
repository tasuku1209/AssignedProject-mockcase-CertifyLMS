<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AiChatConversation;
use App\Models\User;

/**
 * AIチャットメッセージに対する認可ポリシー。
 *
 * - create: 自分が所有するAI相談に対してのみメッセージを送信可
 *
 * AI相談およびメッセージは学習中の受講生のみが利用するため、
 * ロールはStudent、Conversationの所有者はログインユーザー本人であることを確認する。
 */
class AiChatMessagePolicy
{
    public function create(
        User $user,
        AiChatConversation $conversation,
    ): bool {
        return $user->role === UserRole::Student
            && $conversation->user_id === $user->id;
    }
}
