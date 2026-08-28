<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use App\Policies\EnrollmentNotePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * EnrollmentNotePolicy の ability × role × 担当状況 × メモ作成者 × Enrollment 状態を検証する。
 *
 * viewAny / create:
 * - admin: 全 Enrollment 可
 * - coach: 担当資格の Enrollment のみ可
 * - student: 不可
 *
 * update / delete:
 * - admin: 全メモ可
 * - coach: 担当資格かつ自分が作成したメモのみ可
 * - student: 不可
 *
 * soft delete 済み Enrollment は全 ability で不可。
 */
class EnrollmentNotePolicyTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('viewAndCreateMatrix')]
    public function test_view_any_and_create_return_expected_for_role_and_assignment(
        string $actingRole,
        bool $assigned,
        bool $expected,
    ): void {
        // Arrange
        $actor = User::factory()->{$actingRole}()->create();
        $enrollment = Enrollment::factory()->create();

        if ($assigned && $actingRole === 'coach') {
            $this->assignCoach($actor, $enrollment->certification);
        }

        $policy = new EnrollmentNotePolicy;

        // Act
        $viewAny = $policy->viewAny($actor, $enrollment);
        $create = $policy->create($actor, $enrollment);

        // Assert
        $this->assertSame(
            $expected,
            $viewAny,
            "{$actingRole} (assigned=".($assigned ? 'yes' : 'no').') の viewAny は '
                .($expected ? 'true' : 'false').' を返すはず',
        );

        $this->assertSame(
            $expected,
            $create,
            "{$actingRole} (assigned=".($assigned ? 'yes' : 'no').') の create は '
                .($expected ? 'true' : 'false').' を返すはず',
        );
    }

    #[DataProvider('updateAndDeleteMatrix')]
    public function test_update_and_delete_return_expected_for_role_and_note_owner(
        string $actingRole,
        bool $assigned,
        bool $ownNote,
        bool $expected,
    ): void {
        // Arrange
        $actor = User::factory()->{$actingRole}()->create();
        $enrollment = Enrollment::factory()->create();

        if ($assigned && $actingRole === 'coach') {
            $this->assignCoach($actor, $enrollment->certification);
        }

        $author = $ownNote
            ? $actor
            : User::factory()->coach()->create();

        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forCoach($author)
            ->create();

        $policy = new EnrollmentNotePolicy;

        // Act
        $update = $policy->update($actor, $note);
        $delete = $policy->delete($actor, $note);

        // Assert
        $this->assertSame(
            $expected,
            $update,
            "{$actingRole} (assigned=".($assigned ? 'yes' : 'no')
                .', ownNote='.($ownNote ? 'yes' : 'no').') の update は '
                .($expected ? 'true' : 'false').' を返すはず',
        );

        $this->assertSame(
            $expected,
            $delete,
            "{$actingRole} (assigned=".($assigned ? 'yes' : 'no')
                .', ownNote='.($ownNote ? 'yes' : 'no').') の delete は '
                .($expected ? 'true' : 'false').' を返すはず',
        );
    }

    public function test_all_abilities_are_denied_when_enrollment_is_soft_deleted(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        $this->assignCoach($coach, $enrollment->certification);

        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->forCoach($coach)
            ->create();

        $enrollment->delete();

        $policy = new EnrollmentNotePolicy;

        // Act / Assert
        $this->assertFalse($policy->viewAny($admin, $enrollment));
        $this->assertFalse($policy->create($admin, $enrollment));
        $this->assertFalse($policy->update($admin, $note));
        $this->assertFalse($policy->delete($admin, $note));

        $this->assertFalse($policy->viewAny($coach, $enrollment));
        $this->assertFalse($policy->create($coach, $enrollment));
        $this->assertFalse($policy->update($coach, $note));
        $this->assertFalse($policy->delete($coach, $note));
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: bool}>
     */
    public static function viewAndCreateMatrix(): array
    {
        return [
            'admin は全 Enrollment を閲覧できる' => ['admin', false, true],
            'coach は担当資格の Enrollment を閲覧できる' => ['coach', true, true],
            'coach は担当外資格の Enrollment を閲覧できない' => ['coach', false, false],
            'student は担当資格でもメモを閲覧できない' => ['student', true, false],
            'student は担当外資格でもメモを閲覧できない' => ['student', false, false],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: bool, 3: bool}>
     */
    public static function updateAndDeleteMatrix(): array
    {
        return [
            'admin は自分のメモを操作できる' => ['admin', false, true, true],
            'admin は他コーチのメモも操作できる' => ['admin', false, false, true],
            'coach は担当資格の自分のメモを操作できる' => ['coach', true, true, true],
            'coach は担当資格の他コーチのメモを操作できない' => ['coach', true, false, false],
            'coach は担当外資格の自分のメモも操作できない' => ['coach', false, true, false],
            'coach は担当外資格の他コーチのメモも操作できない' => ['coach', false, false, false],
            'student は自分のメモでも操作できない' => ['student', false, true, false],
            'student は他コーチのメモでも操作できない' => ['student', false, false, false],
        ];
    }

    private function assignCoach(User $coach, Certification $certification): void
    {
        $admin = User::factory()->admin()->create();

        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
    }
}
