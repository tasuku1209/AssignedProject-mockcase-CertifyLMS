<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\User;

class CertificatePolicy
{
    /**
     * 修了証のダウンロード認可。
     * admin は全件 / coach は担当資格のみ / student は本人分のみ。
     */
    public function download(User $auth, Certificate $certificate): bool
    {
        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => $certificate->certification
                ->coaches
                ->contains('id', $auth->id),
            UserRole::Student => $certificate->user_id === $auth->id,
        };
    }
}
