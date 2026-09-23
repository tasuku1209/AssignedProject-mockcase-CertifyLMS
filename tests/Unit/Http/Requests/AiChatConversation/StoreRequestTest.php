<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\AiChatConversation;

use App\Http\Requests\AiChatConversation\StoreRequest;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AIチャット相談新規作成 StoreRequest のバリデーション検証。
 * source / section_id / message の valid + invalid を検証する。
 *
 * authorize の Policy 疎通確認は Policy テストで担当するため、本テストでは扱わない。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_valid_payload(): void
    {
        // Arrange
        $section = Section::factory()->create();

        $payload = [
            'source' => 'widget',
            'section_id' => $section->id,
            'message' => 'この問題について教えてください。',
        ];

        // Act
        $validator = Validator::make(
            $payload,
            (new StoreRequest)->rules(),
        );

        // Assert
        $this->assertFalse($validator->fails());
    }

    public function test_validation_passes_without_optional_fields(): void
    {
        // Arrange
        $payload = [
            'source' => 'full-screen',
        ];

        // Act
        $validator = Validator::make(
            $payload,
            (new StoreRequest)->rules(),
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
        $section = Section::factory()->create();

        $payload = array_merge([
            'source' => 'widget',
            'section_id' => $section->id,
            'message' => 'この問題について教えてください。',
        ], $overrides);

        // Act
        $validator = Validator::make(
            $payload,
            (new StoreRequest)->rules(),
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
            'source 未指定でエラー' => [
                ['source' => ''],
                'source',
            ],
            'source 不正値でエラー' => [
                ['source' => 'unknown'],
                'source',
            ],
            'section_id ulid 不正でエラー' => [
                ['section_id' => 'not-ulid'],
                'section_id',
            ],
            'section_id 存在しない ulid でエラー' => [
                ['section_id' => (string) Str::ulid()],
                'section_id',
            ],
            'message 数値でエラー' => [
                ['message' => 12345],
                'message',
            ],
            'message 2001 文字でエラー' => [
                ['message' => str_repeat('a', 2001)],
                'message',
            ],
        ];
    }
}
