<?php

declare(strict_types=1);

namespace App\Http\Requests\MeetingQuota;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 追加面談パック購入開始リクエスト。
 * 受講生が購入する面談パックを 1 項目指定する。
 */
class CreateCheckoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create-meeting-quota-checkout') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'meeting_pack_id' => [
                'required',
                'ulid',
                Rule::exists(MeetingPack::class, 'id')
                    ->where('status', MeetingPackStatus::Published->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'meeting_pack_id' => '面談パック',
        ];
    }
}
