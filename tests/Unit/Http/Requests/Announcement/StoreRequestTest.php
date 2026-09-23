<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * お知らせ配信 FormRequest のバリデーション検証。
 *
 * 配信対象 3 種類（全受講生 / 資格指定 / ユーザー指定）の
 * required_if / prohibited_if の相互作用と、
 * title / body / target_type の基本バリデーションを網羅する。
 *
 * authorize は admin のみ true。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_students_announcement_passes_without_target_ids(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->post(
            route('admin.announcements.store'),
            [
                'title' => '全受講生向けのお知らせ',
                'body' => '全受講生向けの本文です。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ],
        );

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);

        $this->assertDatabaseHas('announcements', [
            'title' => '全受講生向けのお知らせ',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ]);
    }

    public function test_certification_announcement_passes_with_target_certification(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        // Act
        $response = $this->actingAs($admin)->post(
            route('admin.announcements.store'),
            [
                'title' => '資格指定のお知らせ',
                'body' => '指定資格の受講生向けの本文です。',
                'target_type' => AnnouncementTargetType::Certification->value,
                'target_certification_id' => $certification->id,
            ],
        );

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);

        $this->assertDatabaseHas('announcements', [
            'title' => '資格指定のお知らせ',
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $certification->id,
        ]);
    }

    public function test_user_announcement_passes_with_target_user(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $student = User::factory()
            ->student()
            ->create();

        // Act
        $response = $this->actingAs($admin)->post(
            route('admin.announcements.store'),
            [
                'title' => 'ユーザー指定のお知らせ',
                'body' => '指定受講生向けの本文です。',
                'target_type' => AnnouncementTargetType::User->value,
                'target_user_id' => $student->id,
            ],
        );

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);

        $this->assertDatabaseHas('announcements', [
            'title' => 'ユーザー指定のお知らせ',
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $student->id,
        ]);
    }

    public function test_certification_target_without_certification_fails_required_if(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(
            route('admin.announcements.store'),
            [
                'title' => '資格指定のお知らせ',
                'body' => '本文です。',
                'target_type' => AnnouncementTargetType::Certification->value,
            ],
        );

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(
            'target_certification_id',
        );
    }

    public function test_user_target_without_user_fails_required_if(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(
            route('admin.announcements.store'),
            [
                'title' => 'ユーザー指定のお知らせ',
                'body' => '本文です。',
                'target_type' => AnnouncementTargetType::User->value,
            ],
        );

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(
            'target_user_id',
        );
    }

    public function test_all_students_with_certification_fails_prohibited_if(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        // Act
        $response = $this->actingAs($admin)->postJson(
            route('admin.announcements.store'),
            [
                'title' => '全受講生向けのお知らせ',
                'body' => '本文です。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
                'target_certification_id' => $certification->id,
            ],
        );

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(
            'target_certification_id',
        );
    }

    public function test_all_students_with_user_fails_prohibited_if(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $student = User::factory()
            ->student()
            ->create();

        // Act
        $response = $this->actingAs($admin)->postJson(
            route('admin.announcements.store'),
            [
                'title' => '全受講生向けのお知らせ',
                'body' => '本文です。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
                'target_user_id' => $student->id,
            ],
        );

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(
            'target_user_id',
        );
    }

    public function test_certification_target_with_user_fails_prohibited_if(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $student = User::factory()
            ->student()
            ->create();

        // Act
        $response = $this->actingAs($admin)->postJson(
            route('admin.announcements.store'),
            [
                'title' => '資格指定のお知らせ',
                'body' => '本文です。',
                'target_type' => AnnouncementTargetType::Certification->value,
                'target_certification_id' => $certification->id,
                'target_user_id' => $student->id,
            ],
        );

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(
            'target_user_id',
        );
    }

    public function test_user_target_with_certification_fails_prohibited_if(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $student = User::factory()
            ->student()
            ->create();

        // Act
        $response = $this->actingAs($admin)->postJson(
            route('admin.announcements.store'),
            [
                'title' => 'ユーザー指定のお知らせ',
                'body' => '本文です。',
                'target_type' => AnnouncementTargetType::User->value,
                'target_certification_id' => $certification->id,
                'target_user_id' => $student->id,
            ],
        );

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(
            'target_certification_id',
        );
    }

    public function test_nonexistent_certification_fails_exists_rule(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(
            route('admin.announcements.store'),
            [
                'title' => '資格指定のお知らせ',
                'body' => '本文です。',
                'target_type' => AnnouncementTargetType::Certification->value,
                'target_certification_id' => (string) Str::ulid(),
            ],
        );

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(
            'target_certification_id',
        );
    }

    public function test_nonexistent_user_fails_exists_rule(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(
            route('admin.announcements.store'),
            [
                'title' => 'ユーザー指定のお知らせ',
                'body' => '本文です。',
                'target_type' => AnnouncementTargetType::User->value,
                'target_user_id' => (string) Str::ulid(),
            ],
        );

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(
            'target_user_id',
        );
    }

    #[DataProvider('invalidBasicPayloads')]
    public function test_validation_fails_for_invalid_basic_fields(
        array $payload,
        string $expectedErrorField,
    ): void {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(
            route('admin.announcements.store'),
            $payload,
        );

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(
            $expectedErrorField,
        );
    }

    public function test_authorize_returns_false_for_non_admin(): void
    {
        // Arrange
        $coach = User::factory()
            ->coach()
            ->create();

        // Act
        $response = $this->actingAs($coach)->postJson(
            route('admin.announcements.store'),
            [
                'title' => 'お知らせ',
                'body' => '本文です。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ],
        );

        // Assert: role Middleware または FormRequest authorize で 403
        $response->assertForbidden();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidBasicPayloads(): array
    {
        return [
            'タイトル未指定で 422' => [
                [
                    'body' => '本文です。',
                    'target_type' => AnnouncementTargetType::AllStudents->value,
                ],
                'title',
            ],

            'タイトル 200 文字超で 422' => [
                [
                    'title' => str_repeat('a', 201),
                    'body' => '本文です。',
                    'target_type' => AnnouncementTargetType::AllStudents->value,
                ],
                'title',
            ],

            '本文未指定で 422' => [
                [
                    'title' => 'お知らせ',
                    'target_type' => AnnouncementTargetType::AllStudents->value,
                ],
                'body',
            ],

            '本文 5000 文字超で 422' => [
                [
                    'title' => 'お知らせ',
                    'body' => str_repeat('a', 5001),
                    'target_type' => AnnouncementTargetType::AllStudents->value,
                ],
                'body',
            ],

            '配信対象未指定で 422' => [
                [
                    'title' => 'お知らせ',
                    'body' => '本文です。',
                ],
                'target_type',
            ],

            '配信対象の許容外値で 422' => [
                [
                    'title' => 'お知らせ',
                    'body' => '本文です。',
                    'target_type' => 'invalid_target',
                ],
                'target_type',
            ],
        ];
    }
}
