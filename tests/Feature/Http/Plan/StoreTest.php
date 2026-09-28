<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_plan_create_page(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.plans.create'));

        // Assert
        $response->assertOk();
        $response->assertViewIs('plan.management.create');
    }

    public function test_admin_can_create_plan_as_draft_and_is_redirected_with_flash_message(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)
            ->post(route('admin.plans.store'), [
                'name' => 'スタンダードプラン',
                'description' => '標準的な学習プラン',
                'duration_days' => 90,
                'default_meeting_quota' => 12,
                'sort_order' => 20,
            ]);

        // Assert
        $response
            ->assertRedirect()
            ->assertSessionHas('success', '受講プランを作成しました。');

        $plan = Plan::firstOrFail();

        $this->assertSame('スタンダードプラン', $plan->name);
        $this->assertSame('標準的な学習プラン', $plan->description);
        $this->assertSame(90, $plan->duration_days);
        $this->assertSame(12, $plan->default_meeting_quota);
        $this->assertSame(20, $plan->sort_order);
        $this->assertSame(PlanStatus::Draft, $plan->status);
        $this->assertSame($admin->id, $plan->created_by_user_id);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
    }

    public function test_coach_cannot_view_plan_create_page(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        // Act
        $response = $this->actingAs($coach)
            ->get(route('admin.plans.create'));

        // Assert
        $response->assertForbidden();
    }

    public function test_coach_cannot_create_plan(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        // Act
        $response = $this->actingAs($coach)
            ->postJson(route('admin.plans.store'), [
                'name' => 'unauthorized',
                'description' => null,
                'duration_days' => 90,
                'default_meeting_quota' => 12,
                'sort_order' => 0,
            ]);

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseMissing('plans', [
            'name' => 'unauthorized',
        ]);
    }

    public function test_store_returns_validation_error_when_name_is_missing(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)
            ->postJson(route('admin.plans.store'), [
                'name' => '',
                'duration_days' => 90,
                'default_meeting_quota' => 12,
            ]);

        // Assert
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }
}
