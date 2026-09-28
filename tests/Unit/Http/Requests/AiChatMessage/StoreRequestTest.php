<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\AiChatMessage;

use App\Http\Requests\AiChatMessage\StoreRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AIチャットメッセージ送信 StoreRequest のバリデーション検証。
 * content の required / string / max:2000 を検証する。
 *
 * authorize の Policy 疎通確認は Policy テストで担当するため、本テストでは扱わない。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_valid_payload(): void
    {
        $payload = [
            'content' => 'この問題について教えてください。',
        ];

        $validator = Validator::make(
            $payload,
            (new StoreRequest)->rules(),
        );

        $this->assertFalse($validator->fails());
    }

    public function test_validation_passes_with_2000_character_content(): void
    {
        $payload = [
            'content' => str_repeat('a', 2000),
        ];

        $validator = Validator::make(
            $payload,
            (new StoreRequest)->rules(),
        );

        $this->assertFalse($validator->fails());
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(
        array $overrides,
        string $expectedErrorField,
    ): void {
        $payload = array_merge([
            'content' => 'この問題について教えてください。',
        ], $overrides);

        $validator = Validator::make(
            $payload,
            (new StoreRequest)->rules(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            $expectedErrorField,
            $validator->errors()->toArray(),
        );
    }

    public static function invalidPayloads(): array
    {
        return [
            'content 未指定でエラー' => [
                ['content' => ''],
                'content',
            ],
            'content 数値でエラー' => [
                ['content' => 12345],
                'content',
            ],
            'content 2001 文字でエラー' => [
                ['content' => str_repeat('a', 2001)],
                'content',
            ],
        ];
    }
}
