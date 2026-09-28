<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Plan;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * プランマスタ新規作成 FormRequest
 * (`app/Http/Requests/Plan/StoreRequest.php`) のバリデーション検証。
 *
 * 必須 / 型 / 文字数 / 数値レンジを網羅する。
 * authorize は PlanPolicy::create により admin のみ通過することを併せて検証する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_full_valid_payload(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), [
            'name' => 'スタンダードプラン',
            'description' => '標準的な学習期間と面談回数を提供するプラン',
            'duration_days' => 90,
            'default_meeting_quota' => 12,
            'sort_order' => 10,
        ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('plans', [
            'name' => 'スタンダードプラン',
            'duration_days' => 90,
            'default_meeting_quota' => 12,
            'sort_order' => 10,
        ]);
    }

    public function test_validation_passes_without_optional_fields(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), [
            'name' => 'シンプルプラン',
            'duration_days' => 30,
            'default_meeting_quota' => 0,
        ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionDoesntHaveErrors();
    }

    #[DataProvider('invalidFieldPayloads')]
    public function test_validation_fails(string $invalidField, mixed $invalidValue): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $payload = array_merge([
            'name' => 'Sample Plan',
            'duration_days' => 90,
            'default_meeting_quota' => 12,
        ], [$invalidField => $invalidValue]);

        // Act
        $response = $this->actingAs($admin)->postJson(
            route('admin.plans.store'),
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
        $response = $this->actingAs($coach)->postJson(route('admin.plans.store'), [
            'name' => 'Sample Plan',
            'duration_days' => 90,
            'default_meeting_quota' => 12,
        ]);

        // Assert
        $response->assertForbidden();
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function invalidFieldPayloads(): array
    {
        return [
            'name 未指定で 422' => ['name', ''],
            'name 101 文字で 422' => ['name', str_repeat('a', 101)],
            'description 2001 文字で 422' => ['description', str_repeat('b', 2001)],
            'duration_days 0 で 422' => ['duration_days', 0],
            'duration_days 3651 で 422' => ['duration_days', 3651],
            'duration_days 非整数で 422' => ['duration_days', 'abc'],
            'default_meeting_quota -1 で 422' => ['default_meeting_quota', -1],
            'default_meeting_quota 1001 で 422' => ['default_meeting_quota', 1001],
            'default_meeting_quota 非整数で 422' => ['default_meeting_quota', 'abc'],
            'sort_order 負数で 422' => ['sort_order', -1],
            'sort_order 65536 で 422' => ['sort_order', 65536],
            'sort_order 非整数で 422' => ['sort_order', 'abc'],
        ];
    }
}
