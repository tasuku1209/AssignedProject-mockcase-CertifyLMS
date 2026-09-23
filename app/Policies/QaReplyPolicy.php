<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

class QaReplyPolicy
{
    /**
     * 回答を投稿できるか。
     *
     * - student: 公開済資格のスレッドに投稿可能
     * - coach: 担当資格のスレッドに投稿可能
     * - admin: 回答不可
     */
    public function create(User $auth, QaThread $thread): bool
    {
        if ($auth->role === UserRole::Student) {
            return $thread->certification?->status === CertificationStatus::Published;
        }

        if ($auth->role === UserRole::Coach) {
            return $thread->certification?->status === CertificationStatus::Published
                && $this->assignedCoach($auth, $thread);
        }

        return false;
    }

    /**
     * 自分の回答を編集できるか。
     */
    public function update(User $auth, QaReply $reply): bool
    {
        return in_array($auth->role, [
            UserRole::Student,
            UserRole::Coach,
        ], true)
            && $auth->id === $reply->user_id;
    }

    /**
     * 自分の回答を削除できるか。
     */
    public function delete(User $auth, QaReply $reply): bool
    {
        if ($auth->role === UserRole::Admin) {
            return true;
        }

        return in_array($auth->role, [
            UserRole::Student,
            UserRole::Coach,
        ], true)
            && $auth->id === $reply->user_id;
    }

    /**
     * コーチがスレッドの資格を担当しているか。
     */
    private function assignedCoach(
        User $coach,
        QaThread $thread
    ): bool {
        return $thread->certification
            ->coaches()
            ->where('users.id', $coach->id)
            ->exists();
    }
}
