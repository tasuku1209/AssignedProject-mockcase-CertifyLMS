<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_draft_plan_without_users_and_is_redirected_with_flash_message(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        // Act
        $response = $this->actingAs($admin)
            ->delete(route('admin.plans.destroy', $plan));

        // Assert
        $response
            ->assertRedirect()
            ->assertSessionHas('success', '受講プランを削除しました。');

        $this->assertDatabaseMissing('plans', [
            'id' => $plan->id,
        ]);
    }

    public function test_destroy_rejects_published_plan(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        // Act
        $response = $this->actingAs($admin)
            ->deleteJson(route('admin.plans.destroy', $plan));

        // Assert
        $response->assertStatus(409);

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
        ]);
    }

    public function test_destroy_rejects_archived_plan(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create();

        // Act
        $response = $this->actingAs($admin)
            ->deleteJson(route('admin.plans.destroy', $plan));

        // Assert
        $response->assertStatus(409);

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
        ]);
    }

    public function test_destroy_rejects_plan_with_attached_student(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        User::factory()
            ->student()
            ->withPlan($plan)
            ->create();

        // Act
        $response = $this->actingAs($admin)
            ->deleteJson(route('admin.plans.destroy', $plan));

        // Assert
        $response->assertStatus(409);

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
        ]);
    }

    public function test_coach_cannot_delete_plan(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->draft()->create();

        // Act
        $response = $this->actingAs($coach)
            ->deleteJson(route('admin.plans.destroy', $plan));

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
        ]);
    }
}
