<?php

declare(strict_types=1);

namespace App\UseCases\AiChatConversation;

use App\Models\AiChatConversation;
use Illuminate\Support\Facades\DB;

final class UpdateAction
{
    /**
     * AIチャット相談のタイトルを更新する。
     *
     * @param array{title: string} $validated
     */
    public function __invoke(
        AiChatConversation $conversation,
        array $validated,
    ): AiChatConversation {
        return DB::transaction(function () use ($conversation, $validated) {
            $conversation->update([
                'title' => $validated['title'],
            ]);

            return $conversation->fresh();
        });
    }
}
