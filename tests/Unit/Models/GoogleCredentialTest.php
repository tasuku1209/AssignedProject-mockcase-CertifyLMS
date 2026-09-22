<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\GoogleCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * GoogleCredential モデルのリレーション・Cast を検証する Unit テスト。
 * user リレーション + 2つの datetime cast を確認する。
 */
class GoogleCredentialTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relation_returns_connected_user(): void
    {
        // Arrange
        $user = User::factory()->coach()->create();
        $credential = GoogleCredential::factory()->for($user)->create();

        // Act
        $connectedUser = $credential->user;

        // Assert
        $this->assertTrue($connectedUser->is($user));
    }

    public function test_token_expires_at_cast_returns_carbon(): void
    {
        // Arrange
        $credential = GoogleCredential::factory()->create();

        // Act
        $fresh = $credential->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->token_expires_at);
    }

    public function test_connected_at_cast_returns_carbon(): void
    {
        // Arrange
        $credential = GoogleCredential::factory()->create();

        // Act
        $fresh = $credential->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->connected_at);
    }
}
