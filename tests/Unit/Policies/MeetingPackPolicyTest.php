<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\MeetingPack;
use App\Models\User;
use App\Policies\MeetingPackPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingPackPolicyTest extends TestCase
{
    use RefreshDatabase;

    private MeetingPackPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new MeetingPackPolicy;
    }

    public function test_admin_can_perform_all_meeting_pack_management_actions(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()->create();

        // Assert
        $this->assertTrue($this->policy->viewAny($admin));
        $this->assertTrue($this->policy->view($admin, $meetingPack));
        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($admin, $meetingPack));
        $this->assertTrue($this->policy->delete($admin, $meetingPack));
        $this->assertTrue($this->policy->publish($admin, $meetingPack));
        $this->assertTrue($this->policy->archive($admin, $meetingPack));
        $this->assertTrue($this->policy->unarchive($admin, $meetingPack));
    }

    public function test_coach_cannot_perform_meeting_pack_management_actions(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $meetingPack = MeetingPack::factory()->create();

        // Assert
        $this->assertFalse($this->policy->viewAny($coach));
        $this->assertFalse($this->policy->view($coach, $meetingPack));
        $this->assertFalse($this->policy->create($coach));
        $this->assertFalse($this->policy->update($coach, $meetingPack));
        $this->assertFalse($this->policy->delete($coach, $meetingPack));
        $this->assertFalse($this->policy->publish($coach, $meetingPack));
        $this->assertFalse($this->policy->archive($coach, $meetingPack));
        $this->assertFalse($this->policy->unarchive($coach, $meetingPack));
    }

    public function test_student_cannot_perform_meeting_pack_management_actions(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $meetingPack = MeetingPack::factory()->create();

        // Assert
        $this->assertFalse($this->policy->viewAny($student));
        $this->assertFalse($this->policy->view($student, $meetingPack));
        $this->assertFalse($this->policy->create($student));
        $this->assertFalse($this->policy->update($student, $meetingPack));
        $this->assertFalse($this->policy->delete($student, $meetingPack));
        $this->assertFalse($this->policy->publish($student, $meetingPack));
        $this->assertFalse($this->policy->archive($student, $meetingPack));
        $this->assertFalse($this->policy->unarchive($student, $meetingPack));
    }
}
