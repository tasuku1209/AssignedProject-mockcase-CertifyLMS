<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 開発用アバターシーダー。
 *
 * UserSeeder で作成された固定ユーザーと、
 * 本 Seeder で作成する固定の修了済み受講生にアバターを設定する。
 *
 * UserSeeder / その他の依存 Seeder の実行後、最後に実行する。
 */
class AvatarSeeder extends Seeder
{
    private const ADMIN_AVATAR_URL = '/storage/avatars/admin-avatar-blue.png';

    private const COACH_AVATAR_URL = '/storage/avatars/coach-avatar-yellow.png';

    private const STUDENT_AVATAR_URL = '/storage/avatars/student-avatar-red.png';

    private const GRADUATED_STUDENT_AVATAR_URL
        = '/storage/avatars/student-graduated-avatar-green.png';

    public function run(): void
    {
        // UserSeeder が作成する固定ユーザーを取得する。
        $fixedUsers = User::query()
            ->whereIn('email', [
                'admin@certify-lms.test',
                'coach@certify-lms.test',
                'student@certify-lms.test',
            ])
            ->get();

        // 3 名すべてが存在しなければ、前提となる UserSeeder の実行を促して終了する。
        if ($fixedUsers->count() !== 3) {
            $this->command?->warn(
                'AvatarSeeder: 固定ユーザーが揃っていません。先に UserSeeder を実行してください。'
            );

            return;
        }

        // 固定の修了済み受講生を作成する。
        $graduatedStudent = User::factory()
            ->student()
            ->graduated()
            ->state([
                'name' => '受講生（アバター確認用、修了済み）',
                'email' => 'student-graduated@certify-lms.test',
                'password' => Hash::make('password'),
                'profile_setup_completed' => true,
                'email_verified_at' => now(),
                'plan_expires_at' => now()->subDays(30),
            ])
            ->create();

        // 固定ユーザー 3 名にそれぞれ対応するアバターを設定する。
        $this->attachAvatars($fixedUsers);

        // 本 Seeder で作成した修了済み受講生にアバターを設定する。
        $graduatedStudent->update([
            'avatar_url' => self::GRADUATED_STUDENT_AVATAR_URL,
        ]);
    }

    /**
     * 指定された固定ユーザーに、それぞれ対応するアバター画像を紐づける。
     *
     * @param Collection<int, User> $users
     */
    private function attachAvatars(Collection $users): void
    {
        $avatarUrls = [
            'admin@certify-lms.test' => self::ADMIN_AVATAR_URL,
            'coach@certify-lms.test' => self::COACH_AVATAR_URL,
            'student@certify-lms.test' => self::STUDENT_AVATAR_URL,
        ];

        $users->each(function (User $user) use ($avatarUrls): void {
            $user->update([
                'avatar_url' => $avatarUrls[$user->email],
            ]);
        });
    }
}
