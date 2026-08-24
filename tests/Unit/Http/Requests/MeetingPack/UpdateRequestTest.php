<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\MeetingPack;

use App\Http\Requests\MeetingPack\UpdateRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 面談パック更新 UpdateRequest の rules() バリデーション検証。
 * name / description / meeting_count / price / stripe_price_id / sort_order
 * の数値レンジ・文字数を網羅する。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload(): void
    {
        // Arrange
        $payload = [
            'name' => '30分面談パック',
            'description' => '解説付き',
            'meeting_count' => 1,
            'price' => 5000,
            'stripe_price_id' => 'price_test_12345',
            'sort_order' => 1,
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
            'name' => '30分面談パック',
            'meeting_count' => 1,
            'price' => 5000,
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
        $payload = [
            'name' => '30分面談パック',
            'meeting_count' => 1,
            'price' => 5000,
        ];

        $payload[$field] = $value;

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
            'name 未指定で エラー' => ['name', ''],
            'name 101 文字で エラー' => ['name', str_repeat('a', 101)],

            'description 2001 文字で エラー' => [
                'description',
                str_repeat('b', 2001),
            ],

            'meeting_count 0 で エラー' => ['meeting_count', 0],
            'meeting_count 101 で エラー' => ['meeting_count', 101],
            'meeting_count 非整数で エラー' => ['meeting_count', 'abc'],

            'price 負数で エラー' => ['price', -1],
            'price 1000001 で エラー' => ['price', 1000001],
            'price 非整数で エラー' => ['price', 'abc'],

            'stripe_price_id 256 文字で エラー' => [
                'stripe_price_id',
                str_repeat('c', 256),
            ],

            'sort_order 負数で エラー' => ['sort_order', -1],
            'sort_order 65536 で エラー' => ['sort_order', 65536],
            'sort_order 非整数で エラー' => ['sort_order', 'abc'],
        ];
    }
}
