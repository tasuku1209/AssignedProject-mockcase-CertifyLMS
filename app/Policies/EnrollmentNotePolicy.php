<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;

/**
 * 受講登録メモの認可ルール。
 *
 * - admin: 任意の受講登録のメモを閲覧・追加・編集・削除可
 * - coach: 担当資格の受講登録のみ閲覧・追加可
 * - coach: 自分が作成したメモのみ編集・削除可
 * - student: メモに関する操作をすべて拒否
 *
 * soft delete 済みの Enrollment はメモ操作の対象外とする。
 */
class EnrollmentNotePolicy
{
    /**
     * 対象 Enrollment のメモ一覧を閲覧できるか。
     */
    public function viewAny(User $auth, Enrollment $enrollment): bool
    {
        if ($enrollment->trashed()) {
            return false;
        }

        return $this->canManage($auth, $enrollment);
    }

    /**
     * 対象 Enrollment にメモを作成できるか。
     */
    public function create(User $auth, Enrollment $enrollment): bool
    {
        if ($enrollment->trashed()) {
            return false;
        }

        return $this->canManage($auth, $enrollment);
    }

    /**
     * メモを更新できるか。
     *
     * 親 Enrollment が通常のリレーションで取得できない場合は操作不可。
     */
    public function update(User $auth, EnrollmentNote $note): bool
    {
        $enrollment = $note->enrollment;

        if ($enrollment === null) {
            return false;
        }

        if (! $this->canManage($auth, $enrollment)) {
            return false;
        }

        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => $note->user_id === $auth->id,
            default => false,
        };
    }

    /**
     * メモを削除できるか。
     *
     * 親 Enrollment が通常のリレーションで取得できない場合は操作不可。
     */
    public function delete(User $auth, EnrollmentNote $note): bool
    {
        $enrollment = $note->enrollment;

        if ($enrollment === null) {
            return false;
        }

        if (! $this->canManage($auth, $enrollment)) {
            return false;
        }

        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => $note->user_id === $auth->id,
            default => false,
        };
    }

    /**
     * ユーザーが対象受講登録のメモを扱えるか。
     *
     * - admin: 全受講登録可
     * - coach: 担当資格の受講登録のみ可
     * - student: 不可
     */
    private function canManage(User $auth, Enrollment $enrollment): bool
    {
        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => $this->isAssignedCoach($enrollment, $auth),
            default => false,
        };
    }

    /**
     * コーチが対象受講登録の資格を現在担当しているか。
     */
    private function isAssignedCoach(Enrollment $enrollment, User $coach): bool
    {
        $enrollment->loadMissing('certification.coaches');

        return $enrollment->certification?->coaches->contains('id', $coach->id) ?? false;
    }
}
