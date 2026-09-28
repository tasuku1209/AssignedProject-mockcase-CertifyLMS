<?php

declare(strict_types=1);

namespace App\UseCases\AiChatConversation;

use App\Models\AiChatConversation;
use Illuminate\Support\Facades\DB;

final class DestroyAction
{
    /**
     * AIチャット相談を削除する。
     */
    public function __invoke(AiChatConversation $conversation): void
    {
        DB::transaction(fn () => $conversation->delete());
    }
}
