<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChatConversation;

use App\Models\AiChatConversation;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * AIチャット相談のタイトルを更新する権限を確認する。
     */
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        return $conversation instanceof AiChatConversation
            && ($this->user()?->can('update', $conversation) ?? false);
    }

    /**
     * バリデーションルールを定義する。
     */
    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:100',
            ],
        ];
    }

    /**
     * バリデーションエラーメッセージの属性名を定義する。
     */
    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
        ];
    }
}
