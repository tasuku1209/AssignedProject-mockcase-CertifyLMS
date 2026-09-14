<?php

declare(strict_types=1);

namespace App\UseCases\AiChatConversation;

use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class StoreAction
{
    /**
     * AIチャット相談を新規作成する。
     *
     * @param array{
     *     source: string,
     *     section_id?: ?string,
     *     message?: ?string
     * } $validated
     */
    public function __invoke(User $user, array $validated): AiChatConversation
    {
        return DB::transaction(fn () => AiChatConversation::create([
            'user_id' => $user->id,
            'enrollment_id' => null,
            'section_id' => $validated['section_id'] ?? null,
            'title' => null,
            'auto_title_enabled' => true,
            'last_message_at' => null,
        ]));
    }
}
