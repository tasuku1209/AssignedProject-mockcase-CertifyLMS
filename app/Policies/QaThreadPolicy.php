<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;

class QaThreadPolicy
{
    /**
     * 質問掲示板一覧を閲覧できるか。
     *
     * - admin: 全資格
     * - coach: 担当資格
     * - student: 公開済資格
     */
    public function viewAny(User $auth): bool
    {
        return in_array($auth->role, [
            UserRole::Admin,
            UserRole::Coach,
            UserRole::Student,
        ], true);
    }

    /**
     * スレッドを閲覧できるか。
     *
     * - admin: 全資格
     * - coach: 担当資格
     * - student: 公開済資格
     */
    public function view(User $auth, QaThread $thread): bool
    {
        if ($auth->role === UserRole::Admin) {
            return true;
        }

        if ($auth->role === UserRole::Coach) {
            return $this->assignedCoach($auth, $thread->certification);
        }

        if ($auth->role === UserRole::Student) {
            return $thread->certification?->status === CertificationStatus::Published;
        }

        return false;
    }

    /**
     * 質問を投稿できるか。
     *
     * 受講生のみ。
     * 資格自体の公開状態については、投稿時にController/Request側でも確認する。
     */
    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Student;
    }

    /**
     * 自分の質問を編集できるか。
     *
     * - 受講生
     * - 自分が投稿したスレッド
     */
    public function update(User $auth, QaThread $thread): bool
    {
        return $auth->role === UserRole::Student
            && $auth->id === $thread->user_id;
    }

    /**
     * 自分の質問を削除できるか。
     */
    public function delete(User $auth, QaThread $thread): bool
    {
        return $auth->role === UserRole::Student
            && $auth->id === $thread->user_id;
    }

    /**
     * 自分の質問を解決済みにできるか。
     */
    public function resolve(User $auth, QaThread $thread): bool
    {
        return $auth->role === UserRole::Student
            && $auth->id === $thread->user_id;
    }

    /**
     * 自分の質問を未解決に戻せるか。
     */
    public function unresolve(User $auth, QaThread $thread): bool
    {
        return $auth->role === UserRole::Student
            && $auth->id === $thread->user_id;
    }

    /**
     * コーチが資格の担当者か。
     */
    private function assignedCoach(
        User $coach,
        Certification $certification
    ): bool {
        return $certification
            ->coaches()
            ->where('users.id', $coach->id)
            ->exists();
    }
}
