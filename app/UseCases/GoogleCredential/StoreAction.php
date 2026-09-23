<?php

declare(strict_types=1);

namespace App\UseCases\GoogleCredential;

use App\Models\GoogleCredential;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class StoreAction
{
    /**
     * Google Calendar の連携情報を保存する。
     */
    public function __invoke(
        User $user,
        array $token,
        string $calendarId,
    ): GoogleCredential {
        return DB::transaction(function () use (
            $user,
            $token,
            $calendarId,
        ) {
            return GoogleCredential::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'access_token' => $token['access_token'],
                    'refresh_token' => $token['refresh_token'] ?? null,
                    'token_expires_at' => isset($token['expires_in'])
                        ? now()->addSeconds((int) $token['expires_in'])
                        : null,
                    'calendar_id' => $calendarId,
                    'connected_at' => now(),
                ],
            );
        });
    }
}
