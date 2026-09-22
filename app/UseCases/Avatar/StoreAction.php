<?php

declare(strict_types=1);

namespace App\UseCases\Avatar;

use App\Exceptions\Setting\AvatarStorageException;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class StoreAction
{
    /**
     * @throws AvatarStorageException
     */
    public function __invoke(User $user, UploadedFile $file): User
    {
        $ulid = (string) Str::ulid();
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $path = "avatars/{$ulid}.{$ext}";

        $oldPath = $user->avatar_url;

        try {
            DB::transaction(function () use ($user, $file, $path, $oldPath) {
                Storage::disk('public')->putFileAs(
                    'avatars',
                    $file,
                    basename($path),
                );

                $user->update([
                    'avatar_url' => Storage::disk('public')->url($path),
                ]);

                if ($oldPath !== null) {
                    DB::afterCommit(function () use ($oldPath) {
                        $oldPath = parse_url($oldPath, PHP_URL_PATH);

                        if ($oldPath !== false && $oldPath !== null) {
                            $oldPath = preg_replace(
                                '#^/storage/#',
                                '',
                                $oldPath,
                            );

                            if ($oldPath !== null) {
                                Storage::disk('public')->delete($oldPath);
                            }
                        }
                    });
                }
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);

            throw new AvatarStorageException($e);
        }

        return $user->fresh();
    }
}
