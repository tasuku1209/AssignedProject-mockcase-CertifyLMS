<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChatMessage;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * AIチャットメッセージ送信リクエスト。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        return $conversation instanceof AiChatConversation
            && ($this->user()?->can('create', [
                AiChatMessage::class,
                $conversation,
            ]) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'content' => [
                'required',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'content' => 'メッセージ',
        ];
    }
}
