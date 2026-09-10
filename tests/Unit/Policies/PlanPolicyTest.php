<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Plan;
use App\Models\User;
use App\Policies\PlanPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_perform_all_plan_operations(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create();
        $policy = new PlanPolicy;

        // Assert
        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->view($admin, $plan));
        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->update($admin, $plan));
        $this->assertTrue($policy->delete($admin, $plan));
        $this->assertTrue($policy->publish($admin, $plan));
        $this->assertTrue($policy->archive($admin, $plan));
        $this->assertTrue($policy->unarchive($admin, $plan));
    }

    public function test_coach_and_student_cannot_manage_plans(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $plan = Plan::factory()->create();
        $policy = new PlanPolicy;

        // Assert
        foreach ([$coach, $student] as $user) {
            $this->assertFalse($policy->viewAny($user));
            $this->assertFalse($policy->view($user, $plan));
            $this->assertFalse($policy->create($user));
            $this->assertFalse($policy->update($user, $plan));
            $this->assertFalse($policy->delete($user, $plan));
            $this->assertFalse($policy->publish($user, $plan));
            $this->assertFalse($policy->archive($user, $plan));
            $this->assertFalse($policy->unarchive($user, $plan));
        }
    }
}
