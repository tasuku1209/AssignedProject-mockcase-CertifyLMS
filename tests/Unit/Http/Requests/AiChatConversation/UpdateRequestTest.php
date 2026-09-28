<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\AiChatConversation;

use App\Http\Requests\AiChatConversation\UpdateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AIチャット相談タイトル更新 UpdateRequest のバリデーション検証。
 * title の required / string / max:100 を検証する。
 *
 * authorize の Policy 疎通確認は Policy テストで担当するため、本テストでは扱わない。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_valid_payload(): void
    {
        // Arrange
        $payload = [
            'title' => '学習内容についての質問',
        ];

        // Act
        $validator = Validator::make(
            $payload,
            (new UpdateRequest)->rules(),
        );

        // Assert
        $this->assertFalse($validator->fails());
    }

    public function test_validation_passes_with_100_character_title(): void
    {
        // Arrange
        $payload = [
            'title' => str_repeat('a', 100),
        ];

        // Act
        $validator = Validator::make(
            $payload,
            (new UpdateRequest)->rules(),
        );

        // Assert
        $this->assertFalse($validator->fails());
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(
        array $overrides,
        string $expectedErrorField,
    ): void {
        // Arrange
        $payload = array_merge([
            'title' => '学習内容についての質問',
        ], $overrides);

        // Act
        $validator = Validator::make(
            $payload,
            (new UpdateRequest)->rules(),
        );

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            $expectedErrorField,
            $validator->errors()->toArray(),
        );
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'title 未指定でエラー' => [
                ['title' => ''],
                'title',
            ],
            'title 数値でエラー' => [
                ['title' => 12345],
                'title',
            ],
            'title 101 文字でエラー' => [
                ['title' => str_repeat('a', 101)],
                'title',
            ],
        ];
    }
}
