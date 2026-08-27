<?php

declare(strict_types=1);

namespace App\Http\Requests\Password;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.password.update') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => '現在のパスワード',
            'password' => '新しいパスワード',
            'password_confirmation' => '新しいパスワード(確認)',
        ];
    }
}
