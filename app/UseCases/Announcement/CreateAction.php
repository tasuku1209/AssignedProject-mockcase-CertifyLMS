<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class CreateAction
{
    /**
     * お知らせ作成画面に必要な選択肢を取得する。
     *
     * @return array{
     *     certifications: Collection<int, Certification>,
     *     students: Collection<int, User>
     * }
     */
    public function __invoke(): array
    {
        return [
            'certifications' => Certification::query()
                ->where(
                    'status',
                    CertificationStatus::Published->value
                )
                ->orderBy('name')
                ->get(),

            'students' => User::query()
                ->where('role', UserRole::Student->value)
                ->whereIn('status', [
                    UserStatus::InProgress->value,
                    UserStatus::Graduated->value,
                ])
                ->orderBy('name')
                ->get(),
        ];
    }
}
