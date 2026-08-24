<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Plan;

use App\Http\Requests\Plan\UpdateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * プランマスタ更新 UpdateRequest の rules() バリデーション検証。
 *
 * name / description / duration_days / default_meeting_quota / sort_order
 * の必須・文字数・数値レンジを Validator::make で検証する。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload(): void
    {
        // Arrange
        $payload = [
            'name' => 'スタンダードプラン',
            'description' => '標準的な学習プラン',
            'duration_days' => 90,
            'default_meeting_quota' => 12,
            'sort_order' => 20,
        ];

        // Act
        $validator = Validator::make($payload, (new UpdateRequest)->rules());

        // Assert
        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    public function test_passes_without_optional_fields(): void
    {
        // Arrange
        $payload = [
            'name' => 'スタンダードプラン',
            'duration_days' => 90,
            'default_meeting_quota' => 12,
        ];

        // Act
        $validator = Validator::make($payload, (new UpdateRequest)->rules());

        // Assert
        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    #[DataProvider('invalidCases')]
    public function test_fails_for_invalid_field(string $field, mixed $value): void
    {
        // Arrange
        $payload = [
            'name' => 'スタンダードプラン',
            'duration_days' => 90,
            'default_meeting_quota' => 12,
        ];

        $payload[$field] = $value;

        // Act
        $validator = Validator::make($payload, (new UpdateRequest)->rules());

        // Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey($field, $validator->errors()->toArray());
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function invalidCases(): array
    {
        return [
            'name 未指定で エラー' => ['name', ''],
            'name 101 文字で エラー' => ['name', str_repeat('a', 101)],
            'description 2001 文字で エラー' => ['description', str_repeat('b', 2001)],

            'duration_days 0 で エラー' => ['duration_days', 0],
            'duration_days 3651 で エラー' => ['duration_days', 3651],
            'duration_days 非整数で エラー' => ['duration_days', 'abc'],

            'default_meeting_quota -1 で エラー' => ['default_meeting_quota', -1],
            'default_meeting_quota 1001 で エラー' => ['default_meeting_quota', 1001],
            'default_meeting_quota 非整数で エラー' => ['default_meeting_quota', 'abc'],

            'sort_order 負数で エラー' => ['sort_order', -1],
            'sort_order 65536 で エラー' => ['sort_order', 65536],
            'sort_order 非整数で エラー' => ['sort_order', 'abc'],
        ];
    }
}
