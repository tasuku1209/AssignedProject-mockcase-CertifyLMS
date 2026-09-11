<?php

declare(strict_types=1);

namespace App\UseCases\Avatar;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class DestroyAction
{
    public function __invoke(User $user): void
    {
        DB::transaction(function () use ($user) {
            $oldAvatarUrl = $user->avatar_url;

            $user->update([
                'avatar_url' => null,
            ]);

            if ($oldAvatarUrl !== null) {
                DB::afterCommit(function () use ($oldAvatarUrl) {
                    $path = parse_url($oldAvatarUrl, PHP_URL_PATH);

                    if ($path !== false && $path !== null) {
                        $path = preg_replace('#^/storage/#', '', $path);

                        if ($path !== null) {
                            Storage::disk('public')->delete($path);
                        }
                    }
                });
            }
        });
    }
}
