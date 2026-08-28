<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentNote>
 */
class EnrollmentNoteFactory extends Factory
{
    protected $model = EnrollmentNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'user_id' => User::factory()->coach(),
            'body' => fake()->randomElement([
                '現在の学習状況を確認。基礎分野は順調に理解できています。',
                '模試の結果を確認し、苦手分野を中心に復習するよう案内しました。',
                '次回面談までに問題演習を進めてもらう予定です。',
                '学習ペースに問題はありません。引き続き現在の計画で進めます。',
                '試験日を意識した学習計画について受講生と確認しました。',
                '苦手分野について質問があったため、重点的に復習するよう案内しました。',
                '最近の学習時間が少し減っているため、次回面談で状況を確認します。',
                '受講生から学習方法について相談があり、具体的な進め方を共有しました。',
            ]),
        ];
    }

    /**
     * 対象の受講登録を指定する。
     */
    public function forEnrollment(Enrollment $enrollment): static
    {
        return $this->state(fn () => [
            'enrollment_id' => $enrollment->id,
        ]);
    }

    /**
     * 作成者のコーチを指定する。
     */
    public function forCoach(User $coach): static
    {
        return $this->state(fn () => [
            'user_id' => $coach->id,
        ]);
    }
}
