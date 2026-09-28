<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\MeetingQuota;

use App\Enums\MeetingPackStatus;
use App\Http\Requests\MeetingQuota\CreateCheckoutSessionRequest;
use App\Models\MeetingPack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 追加面談パック購入開始 CreateCheckoutSessionRequest の
 * rules() バリデーション検証。
 *
 * meeting_pack_id の必須・ULID形式・公開済みパックの存在を確認する。
 */
class CreateCheckoutSessionRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_published_meeting_pack_id(): void
    {
        // Arrange
        $meetingPack = MeetingPack::factory()
            ->published()
            ->create();

        $payload = [
            'meeting_pack_id' => $meetingPack->id,
        ];

        // Act
        $validator = Validator::make(
            $payload,
            (new CreateCheckoutSessionRequest)->rules(),
        );

        // Assert
        $this->assertTrue(
            $validator->passes(),
            $validator->errors()->toJson(),
        );
    }

    #[DataProvider('invalidCases')]
    public function test_fails_for_invalid_meeting_pack_id(
        mixed $value,
        ?string $meetingPackStatus = null,
    ): void {
        // Arrange
        if ($meetingPackStatus !== null) {
            $meetingPack = MeetingPack::factory()
                ->create([
                    'status' => $meetingPackStatus,
                ]);

            $value = $meetingPack->id;
        }

        $payload = [
            'meeting_pack_id' => $value,
        ];

        // Act
        $validator = Validator::make(
            $payload,
            (new CreateCheckoutSessionRequest)->rules(),
        );

        // Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey(
            'meeting_pack_id',
            $validator->errors()->toArray(),
        );
    }

    /**
     * @return array<string, array{0: mixed, 1?: string|null}>
     */
    public static function invalidCases(): array
    {
        return [
            'meeting_pack_id 未指定で エラー' => [null],
            'meeting_pack_id 空文字で エラー' => [''],
            'meeting_pack_id 不正な形式で エラー' => ['invalid-id'],
            'meeting_pack_id 存在しないULIDで エラー' => [
                (string) Str::ulid(),
            ],
            'meeting_pack_id 非公開パックで エラー' => [
                null,
                MeetingPackStatus::Draft->value,
            ],
            'meeting_pack_id アーカイブ済みパックで エラー' => [
                null,
                MeetingPackStatus::Archived->value,
            ],
        ];
    }
}
