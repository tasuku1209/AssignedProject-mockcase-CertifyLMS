<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Avatar;

use App\Http\Requests\Avatar\StoreRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * アバター登録 StoreRequest のバリデーション検証。
 * 必須 / ファイル形式 / MIME タイプ / ファイルサイズの検証を行う。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_valid_payload(): void
    {
        // Arrange
        $payload = [
            'avatar' => UploadedFile::fake()->create(
                'avatar.png',
                100,
                'image/png'
            ),
        ];

        // Act
        $validator = Validator::make(
            $payload,
            (new StoreRequest)->rules()
        );

        // Assert
        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    #[DataProvider('invalidCases')]
    public function test_validation_fails(array $payload): void
    {
        // Act
        $validator = Validator::make(
            $payload,
            (new StoreRequest)->rules()
        );

        // Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey(
            'avatar',
            $validator->errors()->toArray()
        );
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidCases(): array
    {
        return [
            'avatar 未指定でエラー' => [
                [],
            ],
            'avatar 文字列でエラー' => [
                ['avatar' => 'avatar.png'],
            ],
            'gif 形式でエラー' => [
                [
                    'avatar' => UploadedFile::fake()->create(
                        'avatar.gif',
                        100,
                        'image/gif'
                    ),
                ],
            ],
            '2049KB でエラー' => [
                [
                    'avatar' => UploadedFile::fake()->create(
                        'avatar.png',
                        2049,
                        'image/png'
                    ),
                ],
            ],
        ];
    }
}
