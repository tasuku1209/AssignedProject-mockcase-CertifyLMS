<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Announcement モデルのリレーション・Cast を検証する Unit テスト。
 *
 * 3 リレーション (targetCertification / targetUser / createdBy) +
 * 2 cast (target_type / dispatched_at) を網羅する。
 */
class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_target_certification_relation_returns_attached_certification(): void
    {
        // Arrange
        $certification = Certification::factory()
            ->published()
            ->create();

        $announcement = Announcement::factory()
            ->forCertification($certification)
            ->create();

        // Act
        $parent = $announcement->targetCertification;

        // Assert
        $this->assertTrue($parent->is($certification));
    }

    public function test_target_user_relation_returns_attached_user(): void
    {
        // Arrange
        $user = User::factory()
            ->student()
            ->create();

        $announcement = Announcement::factory()
            ->forUser($user)
            ->create();

        // Act
        $parent = $announcement->targetUser;

        // Assert
        $this->assertTrue($parent->is($user));
    }

    public function test_created_by_relation_returns_admin_user(): void
    {
        // Arrange
        $admin = User::factory()
            ->admin()
            ->create();

        $announcement = Announcement::factory()
            ->create([
                'created_by_user_id' => $admin->id,
            ]);

        // Act
        $parent = $announcement->createdBy;

        // Assert
        $this->assertTrue($parent->is($admin));
    }

    public function test_target_type_cast_converts_to_enum(): void
    {
        // Arrange
        $announcement = Announcement::factory()
            ->allStudents()
            ->create();

        // Act
        $fresh = $announcement->fresh();

        // Assert
        $this->assertInstanceOf(
            AnnouncementTargetType::class,
            $fresh->target_type,
        );

        $this->assertSame(
            AnnouncementTargetType::AllStudents,
            $fresh->target_type,
        );
    }

    public function test_dispatched_at_cast_returns_carbon(): void
    {
        // Arrange
        $announcement = Announcement::factory()
            ->create([
                'dispatched_at' => now(),
            ]);

        // Act
        $fresh = $announcement->fresh();

        // Assert
        $this->assertInstanceOf(
            Carbon::class,
            $fresh->dispatched_at,
        );
    }
}
