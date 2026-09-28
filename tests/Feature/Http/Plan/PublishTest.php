<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_succeeds_when_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($admin)
            ->post(route('admin.plans.publish', $plan))
            ->assertRedirect();

        $plan->refresh();

        $this->assertSame(PlanStatus::Published, $plan->status);
    }

    public function test_publish_fails_when_already_published(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.plans.publish', $plan))
            ->assertStatus(409);

        $this->assertSame(PlanStatus::Published, $plan->refresh()->status);
    }

    public function test_publish_fails_when_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.plans.publish', $plan))
            ->assertStatus(409);

        $this->assertSame(PlanStatus::Archived, $plan->refresh()->status);
    }

    public function test_archive_succeeds_when_published(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $this->actingAs($admin)
            ->post(route('admin.plans.archive', $plan))
            ->assertRedirect();

        $plan->refresh();

        $this->assertSame(PlanStatus::Archived, $plan->status);
    }

    public function test_archive_fails_when_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.plans.archive', $plan))
            ->assertStatus(409);

        $this->assertSame(PlanStatus::Draft, $plan->refresh()->status);
    }

    public function test_archive_fails_when_already_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.plans.archive', $plan))
            ->assertStatus(409);

        $this->assertSame(PlanStatus::Archived, $plan->refresh()->status);
    }

    public function test_unarchive_succeeds_when_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create();

        $this->actingAs($admin)
            ->post(route('admin.plans.unarchive', $plan))
            ->assertRedirect();

        $plan->refresh();

        $this->assertSame(PlanStatus::Draft, $plan->status);
    }

    public function test_unarchive_fails_when_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.plans.unarchive', $plan))
            ->assertStatus(409);

        $this->assertSame(PlanStatus::Draft, $plan->refresh()->status);
    }

    public function test_unarchive_fails_when_published(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.plans.unarchive', $plan))
            ->assertStatus(409);

        $this->assertSame(PlanStatus::Published, $plan->refresh()->status);
    }
}
