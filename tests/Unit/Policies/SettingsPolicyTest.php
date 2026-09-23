<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\SettingsPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsPolicyTest extends TestCase
{
    use RefreshDatabase;

    private SettingsPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new SettingsPolicy;
    }

    public function test_admin_can_manage_own_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($this->policy->view($admin));
        $this->assertTrue($this->policy->updateProfile($admin));
        $this->assertTrue($this->policy->storeAvatar($admin));
        $this->assertTrue($this->policy->deleteAvatar($admin));
        $this->assertTrue($this->policy->updatePassword($admin));
    }

    public function test_coach_can_manage_own_settings(): void
    {
        $coach = User::factory()->coach()->create();

        $this->assertTrue($this->policy->view($coach));
        $this->assertTrue($this->policy->updateProfile($coach));
        $this->assertTrue($this->policy->storeAvatar($coach));
        $this->assertTrue($this->policy->deleteAvatar($coach));
        $this->assertTrue($this->policy->updatePassword($coach));
    }

    public function test_learning_student_can_manage_own_settings(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->assertTrue($this->policy->view($student));
        $this->assertTrue($this->policy->updateProfile($student));
        $this->assertTrue($this->policy->storeAvatar($student));
        $this->assertTrue($this->policy->deleteAvatar($student));
        $this->assertTrue($this->policy->updatePassword($student));
    }

    public function test_graduated_student_can_manage_own_settings(): void
    {
        $student = User::factory()->student()->graduated()->create();

        $this->assertTrue($this->policy->view($student));
        $this->assertTrue($this->policy->updateProfile($student));
        $this->assertTrue($this->policy->storeAvatar($student));
        $this->assertTrue($this->policy->deleteAvatar($student));
        $this->assertTrue($this->policy->updatePassword($student));
    }
}
