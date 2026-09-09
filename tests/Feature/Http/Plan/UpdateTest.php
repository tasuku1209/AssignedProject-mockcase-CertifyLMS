<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_plan_edit_page(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create();

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.plans.edit', $plan));

        // Assert
        $response
            ->assertOk()
            ->assertViewIs('plan.management.edit')
            ->assertViewHas('plan', function (Plan $viewPlan) use ($plan) {
                return $viewPlan->is($plan);
            });
    }

    public function test_admin_can_update_plan_and_is_redirected_with_flash_message(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create();

        // Act
        $response = $this->actingAs($admin)
            ->put(route('admin.plans.update', $plan), [
                'name' => '更新後プラン',
                'description' => '更新された説明',
                'duration_days' => 180,
                'default_meeting_quota' => 24,
                'sort_order' => 30,
            ]);

        // Assert
        $response
            ->assertRedirect()
            ->assertSessionHas('success', '受講プランを更新しました。');

        $plan->refresh();

        $this->assertSame('更新後プラン', $plan->name);
        $this->assertSame('更新された説明', $plan->description);
        $this->assertSame(180, $plan->duration_days);
        $this->assertSame(24, $plan->default_meeting_quota);
        $this->assertSame(30, $plan->sort_order);
    }

    public function test_update_records_authenticated_user_as_updated_by_user(): void
    {
        // Arrange
        $creator = User::factory()->admin()->create();
        $updater = User::factory()->admin()->create();

        $plan = Plan::factory()->create([
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => $creator->id,
        ]);

        // Act
        $this->actingAs($updater)
            ->put(route('admin.plans.update', $plan), [
                'name' => '更新後プラン',
                'description' => '更新された説明',
                'duration_days' => 180,
                'default_meeting_quota' => 24,
                'sort_order' => 30,
            ])
            ->assertRedirect();

        // Assert
        $plan->refresh();

        $this->assertSame(
            $updater->id,
            $plan->updated_by_user_id,
            '更新したユーザーが updated_by_user_id に記録されるはず',
        );

        $this->assertSame(
            $creator->id,
            $plan->created_by_user_id,
            '更新後も created_by_user_id は変更されないはず',
        );
    }

    public function test_coach_cannot_view_plan_edit_page(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->create();

        // Act
        $response = $this->actingAs($coach)
            ->get(route('admin.plans.edit', $plan));

        // Assert
        $response->assertForbidden();
    }

    public function test_coach_cannot_update_plan(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->create();

        // Act
        $response = $this->actingAs($coach)
            ->putJson(route('admin.plans.update', $plan), [
                'name' => 'unauthorized update',
                'description' => null,
                'duration_days' => 90,
                'default_meeting_quota' => 12,
                'sort_order' => 0,
            ]);

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseMissing('plans', [
            'id' => $plan->id,
            'name' => 'unauthorized update',
        ]);
    }

    public function test_update_returns_validation_error_when_name_is_missing(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create();

        // Act
        $response = $this->actingAs($admin)
            ->putJson(route('admin.plans.update', $plan), [
                'name' => '',
                'duration_days' => 180,
                'default_meeting_quota' => 24,
            ]);

        // Assert
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }
}
