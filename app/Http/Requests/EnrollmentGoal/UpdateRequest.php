<?php

declare(strict_types=1);

namespace App\Http\Requests\EnrollmentGoal;

use App\Models\EnrollmentGoal;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 個人目標の更新リクエスト。
 * Enrollment は変更不可のため、目標内容のみ受け付ける。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $goal = $this->route('goal');

        return $goal instanceof EnrollmentGoal
            && ($this->user()?->can('update', $goal) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'target_date' => ['nullable', 'date', 'after_or_equal:today'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => '目標',
            'target_date' => '目標期日',
            'description' => '詳細',
        ];
    }
}
