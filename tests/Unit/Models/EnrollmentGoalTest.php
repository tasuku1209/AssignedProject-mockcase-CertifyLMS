<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * EnrollmentGoal モデルのリレーション・Cast を検証する Unit テスト。
 *
 * 1 リレーション (enrollment) +
 * 2 cast (target_date / achieved_at) を網羅する。
 *
 * EnrollmentGoal は Enrollment に従属する個人目標であり、
 * 状態は achieved_at の有無によって表現する。
 */
class EnrollmentGoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_relation_returns_parent_enrollment(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();
        $goal = EnrollmentGoal::factory()
            ->for($enrollment)
            ->create();

        // Act
        $parent = $goal->enrollment;

        // Assert
        $this->assertTrue(
            $parent->is($enrollment),
            '親 enrollment と goal->enrollment は一致するはず',
        );
    }

    public function test_target_date_cast_returns_carbon_date(): void
    {
        // Arrange
        $goal = EnrollmentGoal::factory()->create([
            'target_date' => '2026-09-01',
        ]);

        // Act
        $fresh = $goal->fresh();

        // Assert
        $this->assertInstanceOf(
            Carbon::class,
            $fresh->target_date,
            'target_date は Carbon にキャストされるはず',
        );
        $this->assertSame(
            '2026-09-01',
            $fresh->target_date->toDateString(),
        );
    }

    public function test_achieved_at_cast_returns_carbon_datetime(): void
    {
        // Arrange
        $goal = EnrollmentGoal::factory()->achieved()->create();

        // Act
        $fresh = $goal->fresh();

        // Assert
        $this->assertInstanceOf(
            Carbon::class,
            $fresh->achieved_at,
            'achieved_at は Carbon datetime にキャストされるはず',
        );
    }

    public function test_achieved_at_can_be_null_for_unachieved_goal(): void
    {
        // Arrange
        $goal = EnrollmentGoal::factory()->unachieved()->create();

        // Act
        $fresh = $goal->fresh();

        // Assert
        $this->assertNull(
            $fresh->achieved_at,
            '未達成の目標では achieved_at は null のはず',
        );
    }
}
