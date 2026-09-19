<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_plan_show(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()->create([
            'name' => 'スタンダードプラン',
            'description' => '標準的な学習プラン',
            'duration_days' => 90,
            'default_meeting_quota' => 12,
            'sort_order' => 20,
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.plans.show', $plan));

        // Assert
        $response
            ->assertOk()
            ->assertSee($plan->name)
            ->assertSee($plan->description);
    }

    public function test_show_loads_required_plan_relations(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create();

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.plans.show', $plan));

        // Assert
        $response
            ->assertOk()
            ->assertViewHas('plan', function (Plan $loadedPlan) use ($plan) {
                return $loadedPlan->is($plan)
                    && $loadedPlan->relationLoaded('users')
                    && $loadedPlan->relationLoaded('createdBy')
                    && $loadedPlan->relationLoaded('updatedBy');
            });
    }

    public function test_student_cannot_view_plan_show(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $plan = Plan::factory()->create();

        // Act & Assert
        $this->actingAs($student)
            ->get(route('admin.plans.show', $plan))
            ->assertForbidden();
    }
}
