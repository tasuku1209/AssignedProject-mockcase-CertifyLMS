<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\StripeWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * StripeWebhookEvent モデルの Cast を検証する Unit テスト。
 *
 * processed_at の datetime cast を確認する。
 */
class StripeWebhookEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_processed_at_cast_returns_carbon(): void
    {
        // Arrange
        $event = StripeWebhookEvent::query()->create([
            'stripe_event_id' => 'evt_test_001',
            'event_type' => 'checkout.session.completed',
            'processed_at' => now(),
        ]);

        // Act
        $fresh = $event->fresh();

        // Assert
        $this->assertInstanceOf(
            Carbon::class,
            $fresh->processed_at,
            'processed_at は Carbon にキャストされるはず',
        );
    }
}
