<?php

declare(strict_types=1);

namespace App\Http\Requests\Plan;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;

/**
 * プランマスタの更新リクエスト。
 *
 * `status` は状態遷移操作で変更するため、更新では受け付けない。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $plan instanceof Plan
            && ($this->user()?->can('update', $plan) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'default_meeting_quota' => ['required', 'integer', 'min:0', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'プラン名',
            'description' => '説明',
            'duration_days' => '受講期間(日)',
            'default_meeting_quota' => '初期付与面談回数',
            'sort_order' => '並び順',
        ];
    }
}
