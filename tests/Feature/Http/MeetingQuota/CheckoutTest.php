<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingQuota;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_meeting_quota_checkout(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act
        $response = $this->actingAs($student)
            ->get(route('meeting-quota.checkout'));

        // Assert
        $response->assertOk();
        $response->assertViewIs('meeting-quota.checkout-select');
        $response->assertViewHas('plans');
    }

    public function test_checkout_displays_only_published_meeting_packs_in_order(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        $first = MeetingPack::factory()
            ->published()
            ->create([
                'name' => '5回パック',
                'sort_order' => 20,
            ]);

        $second = MeetingPack::factory()
            ->published()
            ->create([
                'name' => '1回パック',
                'sort_order' => 10,
            ]);

        MeetingPack::factory()
            ->draft()
            ->create([
                'name' => '下書きパック',
                'sort_order' => 5,
            ]);

        MeetingPack::factory()
            ->archived()
            ->create([
                'name' => 'アーカイブ済みパック',
                'sort_order' => 1,
            ]);

        // Act
        $response = $this->actingAs($student)
            ->get(route('meeting-quota.checkout'));

        // Assert
        $response->assertOk();
        $response->assertSeeInOrder([
            $second->name,
            $first->name,
        ]);
        $response->assertDontSee('下書きパック');
        $response->assertDontSee('アーカイブ済みパック');

        $plans = $response->viewData('plans');

        $this->assertCount(2, $plans);
        $this->assertSame($second->id, $plans->first()->id);
        $this->assertSame($first->id, $plans->last()->id);
    }

    public function test_coach_cannot_view_meeting_quota_checkout(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        // Act & Assert
        $this->actingAs($coach)
            ->get(route('meeting-quota.checkout'))
            ->assertForbidden();
    }
}
