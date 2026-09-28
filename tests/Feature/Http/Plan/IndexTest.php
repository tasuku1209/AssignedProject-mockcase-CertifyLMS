<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_plans_index(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create([
            'name' => 'スタンダードプラン',
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.plans.index'));

        // Assert
        $response
            ->assertOk()
            ->assertSee($plan->name);
    }

    public function test_admin_can_view_plans_in_sort_order(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $first = Plan::factory()->create([
            'name' => '並び順1',
            'sort_order' => 10,
        ]);

        $second = Plan::factory()->create([
            'name' => '並び順2',
            'sort_order' => 20,
        ]);

        $third = Plan::factory()->create([
            'name' => '並び順3',
            'sort_order' => 30,
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.plans.index'));

        // Assert
        $response
            ->assertOk()
            ->assertSeeInOrder([
                $first->name,
                $second->name,
                $third->name,
            ]);
    }

    public function test_admin_can_paginate_plans(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        Plan::factory()->count(21)->create();

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.plans.index'));

        // Assert
        $response
            ->assertOk()
            ->assertViewHas('plans', function ($plans) {
                return $plans->perPage() === 20
                    && $plans->total() === 21
                    && $plans->currentPage() === 1;
            });
    }

    public function test_admin_can_search_plans_by_name(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $matchedPlan = Plan::factory()->create([
            'name' => 'スタンダードプラン',
        ]);

        Plan::factory()->create([
            'name' => 'ビギナープラン',
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.plans.index', [
                'keyword' => 'スタンダード',
            ]));

        // Assert
        $response
            ->assertOk()
            ->assertSee($matchedPlan->name)
            ->assertDontSee('ビギナープラン');
    }

    public function test_admin_can_filter_plans_by_status(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $publishedPlan = Plan::factory()->published()->create([
            'name' => '公開中プラン',
        ]);

        Plan::factory()->draft()->create([
            'name' => '下書きプラン',
        ]);

        Plan::factory()->archived()->create([
            'name' => 'アーカイブプラン',
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.plans.index', [
                'status' => PlanStatus::Published->value,
            ]));

        // Assert
        $response
            ->assertOk()
            ->assertSee($publishedPlan->name)
            ->assertDontSee('下書きプラン')
            ->assertDontSee('アーカイブプラン');
    }

    public function test_student_cannot_access_admin_plans_index(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act & Assert
        $this->actingAs($student)
            ->get(route('admin.plans.index'))
            ->assertForbidden();
    }
}
