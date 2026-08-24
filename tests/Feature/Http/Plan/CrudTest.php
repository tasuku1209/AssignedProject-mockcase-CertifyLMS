<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_plan_as_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), [
                'name' => 'スタンダードプラン',
                'description' => '標準的な学習プラン',
                'duration_days' => 90,
                'default_meeting_quota' => 12,
                'sort_order' => 20,
            ])
            ->assertRedirect();

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

    public function test_store_uses_default_sort_order_when_omitted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), [
                'name' => 'スタンダードプラン',
                'description' => null,
                'duration_days' => 90,
                'default_meeting_quota' => 12,
            ])
            ->assertRedirect();

        $plan = Plan::firstOrFail();

        $this->assertSame(0, $plan->sort_order);
    }

    public function test_coach_cannot_create_plan(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)
            ->postJson(route('admin.plans.store'), [
                'name' => 'unauthorized',
                'description' => null,
                'duration_days' => 90,
                'default_meeting_quota' => 12,
                'sort_order' => 0,
            ])
            ->assertForbidden();
    }

    public function test_student_cannot_access_admin_plans_index(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('admin.plans.index'))
            ->assertForbidden();
    }

    public function test_admin_can_update_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.plans.update', $plan), [
                'name' => '更新後プラン',
                'description' => '更新された説明',
                'duration_days' => 180,
                'default_meeting_quota' => 24,
                'sort_order' => 30,
            ])
            ->assertRedirect();

        $plan->refresh();

        $this->assertSame('更新後プラン', $plan->name);
        $this->assertSame('更新された説明', $plan->description);
        $this->assertSame(180, $plan->duration_days);
        $this->assertSame(24, $plan->default_meeting_quota);
        $this->assertSame(30, $plan->sort_order);
    }

    public function test_admin_can_update_plan_without_optional_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create([
            'description' => '更新前の説明',
            'sort_order' => 20,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.plans.update', $plan), [
                'name' => '更新後プラン',
                'duration_days' => 180,
                'default_meeting_quota' => 24,
            ])
            ->assertRedirect();

        $plan->refresh();

        $this->assertSame('更新後プラン', $plan->name);
        $this->assertNull($plan->description);
        $this->assertSame(180, $plan->duration_days);
        $this->assertSame(24, $plan->default_meeting_quota);
        $this->assertSame(0, $plan->sort_order);
    }

    public function test_destroy_rejects_plan_with_users(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        User::factory()
            ->student()
            ->withPlan($plan)
            ->create();

        $this->actingAs($admin)
            ->deleteJson(route('admin.plans.destroy', $plan))
            ->assertStatus(409);

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
        ]);
    }

    public function test_destroy_succeeds_when_draft_and_no_users(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($admin)
            ->delete(route('admin.plans.destroy', $plan))
            ->assertRedirect();

        $this->assertDatabaseMissing('plans', [
            'id' => $plan->id,
        ]);
    }

    public function test_destroy_rejects_published_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $this->actingAs($admin)
            ->deleteJson(route('admin.plans.destroy', $plan))
            ->assertStatus(409);

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
        ]);
    }
}
