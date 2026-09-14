<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_announcement_create_page(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.announcements.create'));

        // Assert
        $response
            ->assertOk()
            ->assertViewIs('announcement.management.create')
            ->assertViewHas('certifications')
            ->assertViewHas('students');
    }

    public function test_create_page_contains_only_published_certifications(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $published = Certification::factory()
            ->published()
            ->create();

        $draft = Certification::factory()
            ->draft()
            ->create();

        $archived = Certification::factory()
            ->archived()
            ->create();

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.announcements.create'));

        // Assert
        $certifications = $response->viewData('certifications');

        $this->assertTrue(
            $certifications->contains('id', $published->id)
        );

        $this->assertFalse(
            $certifications->contains('id', $draft->id)
        );

        $this->assertFalse(
            $certifications->contains('id', $archived->id)
        );
    }

    public function test_create_page_contains_only_in_progress_students(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $inProgress = User::factory()
            ->student()
            ->create([
                'status' => UserStatus::InProgress->value,
            ]);

        $graduated = User::factory()
            ->student()
            ->create([
                'status' => UserStatus::Graduated->value,
            ]);

        $invited = User::factory()
            ->student()
            ->create([
                'status' => UserStatus::Invited->value,
            ]);

        $withdrawn = User::factory()
            ->student()
            ->create([
                'status' => UserStatus::Withdrawn->value,
            ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.announcements.create'));

        // Assert
        $students = $response->viewData('students');

        $this->assertTrue(
            $students->contains('id', $inProgress->id)
        );

        $this->assertFalse(
            $students->contains('id', $graduated->id)
        );

        $this->assertFalse(
            $students->contains('id', $invited->id)
        );

        $this->assertFalse(
            $students->contains('id', $withdrawn->id)
        );
    }

    public function test_create_page_orders_certifications_and_students_by_name(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $certificationB = Certification::factory()
            ->published()
            ->create([
                'name' => 'Z 資格',
            ]);

        $certificationA = Certification::factory()
            ->published()
            ->create([
                'name' => 'A 資格',
            ]);

        $studentB = User::factory()
            ->student()
            ->create([
                'name' => '山田太郎',
                'status' => UserStatus::InProgress->value,
            ]);

        $studentA = User::factory()
            ->student()
            ->create([
                'name' => '佐藤花子',
                'status' => UserStatus::InProgress->value,
            ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.announcements.create'));

        // Assert
        $certifications = $response->viewData('certifications');
        $students = $response->viewData('students');

        $certificationIds = $certifications
            ->pluck('id')
            ->all();

        $studentIds = $students
            ->pluck('id')
            ->all();

        $this->assertSame(
            [$certificationA->id, $certificationB->id],
            $certificationIds,
        );

        $this->assertSame(
            [$studentA->id, $studentB->id],
            $studentIds,
        );
    }

    public function test_non_admin_cannot_view_announcement_create_page(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        // Act / Assert
        $this->actingAs($coach)
            ->get(route('admin.announcements.create'))
            ->assertForbidden();
    }
}
