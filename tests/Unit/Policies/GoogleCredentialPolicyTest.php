<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\GoogleCredential;
use App\Models\User;
use App\Policies\GoogleCredentialPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GoogleCredentialPolicy の認可ルールを検証する Unit テスト。
 * connect / callback は coach のみ許可、
 * delete は credential の所有者である coach のみ許可する。
 */
class GoogleCredentialPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_connect_is_allowed_only_for_coach(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $policy = new GoogleCredentialPolicy;

        // Act & Assert
        $this->assertTrue($policy->connect($coach));
        $this->assertFalse($policy->connect($admin));
        $this->assertFalse($policy->connect($student));
    }

    public function test_callback_is_allowed_only_for_coach(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $policy = new GoogleCredentialPolicy;

        // Act & Assert
        $this->assertTrue($policy->callback($coach));
        $this->assertFalse($policy->callback($admin));
        $this->assertFalse($policy->callback($student));
    }

    public function test_delete_is_allowed_only_for_owner_coach(): void
    {
        // Arrange
        $owner = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $credential = GoogleCredential::factory()
            ->forUser($owner)
            ->create();

        $policy = new GoogleCredentialPolicy;

        // Act & Assert
        $this->assertTrue(
            $policy->delete($owner, $credential),
            '自分のGoogle Calendar連携は解除可'
        );

        $this->assertFalse(
            $policy->delete($otherCoach, $credential),
            '他コーチのGoogle Calendar連携は解除不可'
        );

        $this->assertFalse(
            $policy->delete($student, $credential),
            '受講生はGoogle Calendar連携を解除不可'
        );
    }
}
