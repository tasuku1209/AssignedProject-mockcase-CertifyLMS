<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\EnrollmentNote;

use App\Http\Requests\EnrollmentNote\UpdateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 受講登録メモ更新 UpdateRequest の rules() バリデーション検証。
 * body の必須・文字列・最大文字数を正常系 / 異常系で網羅する。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload(): void
    {
        // Arrange
        $payload = [
            'body' => '次回面談までに模試を1回実施するよう案内した。',
        ];

        // Act
        $validator = Validator::make(
            $payload,
            (new UpdateRequest)->rules()
        );

        // Assert
        $this->assertTrue(
            $validator->passes(),
            $validator->errors()->toJson()
        );
    }

    #[DataProvider('invalidCases')]
    public function test_fails_for_invalid_field(string $field, mixed $value): void
    {
        // Arrange
        $payload = [
            'body' => '受講生の学習状況を確認しました。',
        ];

        $payload[$field] = $value;

        // Act
        $validator = Validator::make(
            $payload,
            (new UpdateRequest)->rules()
        );

        // Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey(
            $field,
            $validator->errors()->toArray()
        );
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function invalidCases(): array
    {
        return [
            'body 未指定でエラー' => ['body', ''],
            'body 2001文字でエラー' => ['body', str_repeat('あ', 2001)],
            'body が配列でエラー' => ['body', ['メモ']],
        ];
    }
}
