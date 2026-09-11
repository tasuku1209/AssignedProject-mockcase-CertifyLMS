<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\EnrollmentGoal;

use App\Http\Requests\EnrollmentGoal\UpdateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 個人目標更新 UpdateRequest の rules() バリデーション検証。
 * title / target_date / description の必須・型・文字数を網羅する。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload(): void
    {
        // Arrange
        $payload = [
            'title' => '過去問5年分を解き終える',
            'target_date' => now()->addMonth()->format('Y-m-d'),
            'description' => '毎日少しずつ進める',
        ];

        // Act
        $validator = Validator::make(
            $payload,
            (new UpdateRequest)->rules(),
        );

        // Assert
        $this->assertTrue(
            $validator->passes(),
            $validator->errors()->toJson(),
        );
    }

    public function test_passes_without_optional_fields(): void
    {
        // Arrange
        $payload = [
            'title' => '過去問を解く',
        ];

        // Act
        $validator = Validator::make(
            $payload,
            (new UpdateRequest)->rules(),
        );

        // Assert
        $this->assertTrue(
            $validator->passes(),
            $validator->errors()->toJson(),
        );
    }

    #[DataProvider('invalidCases')]
    public function test_fails_for_invalid_field(string $field, mixed $value): void
    {
        // Arrange
        $payload = array_merge(
            [
                'title' => '有効な目標',
                'target_date' => now()->addMonth()->format('Y-m-d'),
                'description' => '有効な詳細',
            ],
            [$field => $value],
        );

        // Act
        $validator = Validator::make(
            $payload,
            (new UpdateRequest)->rules(),
        );

        // Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey(
            $field,
            $validator->errors()->toArray(),
        );
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function invalidCases(): array
    {
        return [
            'title 空文字で エラー' => ['title', ''],
            'title 101 文字で エラー' => ['title', str_repeat('あ', 101)],
            'target_date 不正な日付で エラー' => ['target_date', 'invalid-date'],
            'target_date 過去の日付で エラー' => [
                'target_date',
                now()->subDay()->format('Y-m-d'),
            ],
            'description 1001 文字で エラー' => ['description', str_repeat('あ', 1001)],
        ];
    }
}
