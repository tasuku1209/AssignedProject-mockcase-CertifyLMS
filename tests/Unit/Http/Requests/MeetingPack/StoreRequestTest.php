<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\MeetingPack;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 面談パック新規作成 FormRequest
 * (`app/Http/Requests/MeetingPack/StoreRequest.php`) のバリデーションを検証する。
 *
 * 必須 / 型 / 文字数 / 数値レンジを網羅する。
 * authorize は admin のみ通過することを併せて検証する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_full_valid_payload(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(
            route('admin.meeting-packs.store'),
            [
                'name' => '30分面談パック',
                'description' => '30分の面談を1回購入できます。',
                'meeting_count' => 1,
                'price' => 5000,
                'stripe_price_id' => 'price_test_12345',
                'sort_order' => 1,
            ],
        );

        // Assert
        $response->assertStatus(302);
        $response->assertSessionDoesntHaveErrors();
    }

    public function test_validation_passes_without_optional_fields(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(
            route('admin.meeting-packs.store'),
            [
                'name' => '30分面談パック',
                'meeting_count' => 1,
                'price' => 5000,
            ],
        );

        // Assert
        $response->assertStatus(302);
        $response->assertSessionDoesntHaveErrors();
    }

    #[DataProvider('invalidFieldPayloads')]
    public function test_validation_fails(string $invalidField, mixed $invalidValue): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $payload = [
            'name' => '30分面談パック',
            'description' => '説明',
            'meeting_count' => 1,
            'price' => 5000,
            'stripe_price_id' => 'price_test_12345',
            'sort_order' => 1,
        ];

        $payload[$invalidField] = $invalidValue;

        // Act
        $response = $this->actingAs($admin)->postJson(
            route('admin.meeting-packs.store'),
            $payload,
        );

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($invalidField);
    }

    public function test_authorize_returns_false_for_non_admin(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        // Act
        $response = $this->actingAs($coach)->postJson(
            route('admin.meeting-packs.store'),
            [
                'name' => '30分面談パック',
                'meeting_count' => 1,
                'price' => 5000,
            ],
        );

        // Assert
        $response->assertForbidden();
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function invalidFieldPayloads(): array
    {
        return [
            'name 未指定' => ['name', ''],
            'name 101文字' => ['name', str_repeat('a', 101)],

            'description 2001文字' => ['description', str_repeat('a', 2001)],

            'meeting_count 0' => ['meeting_count', 0],
            'meeting_count 101' => ['meeting_count', 101],
            'meeting_count 非整数' => ['meeting_count', 'abc'],

            'price 負数' => ['price', -1],
            'price 1000001' => ['price', 1000001],
            'price 非整数' => ['price', 'abc'],

            'stripe_price_id 256文字' => [
                'stripe_price_id',
                str_repeat('a', 256),
            ],

            'sort_order 負数' => ['sort_order', -1],
            'sort_order 65536' => ['sort_order', 65536],
            'sort_order 非整数' => ['sort_order', 'abc'],
        ];
    }
}
