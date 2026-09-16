<?php

declare(strict_types=1);

namespace App\UseCases\AiChatConversation;

use App\Models\AiChatConversation;
use Illuminate\Support\Facades\DB;

final class UpdateAction
{
    /**
     * AIチャット相談のタイトルを手動更新する。
     *
     * 手動でタイトルを編集した場合は、
     * 以後AIによる自動タイトル更新を無効にする。
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
                'auto_title_enabled' => false,
            ]);

            return $conversation->fresh();
        });
    }
}
