<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChatConversation;

use App\Models\AiChatConversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    /**
     * AIチャット相談の新規作成権限を確認する。
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', AiChatConversation::class) ?? false;
    }

    /**
     * バリデーションルールを定義する。
     *
     * - source: 新規相談の起動元
     * - section_id: 教材画面から起動した場合の現在Section
     * - message: 新規相談開始時の最初の質問（任意）
     */
    public function rules(): array
    {
        return [
            'source' => [
                'required',
                Rule::in(['widget', 'full-screen']),
            ],
            'section_id' => [
                'nullable',
                'ulid',
                'exists:sections,id',
            ],
            'message' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * バリデーションエラーメッセージの属性名を定義する。
     */
    public function attributes(): array
    {
        return [
            'message' => '最初の質問',
        ];
    }
}
