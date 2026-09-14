<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AiChatConversation;
use App\Models\User;

/**
 * AIチャット相談(AiChatConversation) の認可ポリシー。
 *
 * - student: 自分が所有するConversationのみ view / update / delete 可
 * - create: 学習中 student のみ可
 * - admin / coach: AIチャットUIおよびConversationへのアクセス不可
 */
class AiChatConversationPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->role === UserRole::Student;
    }

    public function view(User $auth, AiChatConversation $conversation): bool
    {
        return $auth->role === UserRole::Student
            && $conversation->user_id === $auth->id;
    }

    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Student;
    }

    public function update(User $auth, AiChatConversation $conversation): bool
    {
        return $auth->role === UserRole::Student
            && $conversation->user_id === $auth->id;
    }

    public function delete(User $auth, AiChatConversation $conversation): bool
    {
        return $auth->role === UserRole::Student
            && $conversation->user_id === $auth->id;
    }
}
