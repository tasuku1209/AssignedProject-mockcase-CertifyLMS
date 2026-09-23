<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentGoal>
 */
class EnrollmentGoalFactory extends Factory
{
    protected $model = EnrollmentGoal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'title' => fake()->randomElement([
                '過去問を五年分解き終える',
                '教材を最後まで一周する',
                '苦手な分野を重点的に復習する',
                '模擬試験で合格点を取る',
                '毎日一時間学習する',
                '重要な用語を覚える',
                '練習問題をすべて解く',
                '試験範囲を一通り復習する',
                '苦手な問題を解き直す',
                '資格試験の合格に向けて学習を進める',
            ]),
            'target_date' => fake()->dateTimeBetween('now', '+3 months'),
            'description' => fake()->randomElement([
                '間違えた問題は繰り返し解いて、理解を深める。',
                '毎日少しずつ学習時間を確保して、計画的に進める。',
                '苦手な分野を中心に復習して、理解できていない部分を減らす。',
                '一度解いた問題も時間を置いてからもう一度確認する。',
                '教材の内容を確認しながら、実際に問題を解いて理解度を確認する。',
                '模擬試験で間違えた問題を振り返り、同じ間違いをしないようにする。',
                '学習の進み具合を確認しながら、無理のないペースで取り組む。',
            ]),
            'achieved_at' => null,
        ];
    }

    /**
     * 達成済みの目標。
     */
    public function achieved(): static
    {
        return $this->state(fn () => [
            'achieved_at' => now(),
        ]);
    }

    /**
     * 未達成の目標。
     */
    public function unachieved(): static
    {
        return $this->state(fn () => [
            'achieved_at' => null,
        ]);
    }

    /**
     * 期日なしの目標。
     */
    public function withoutTargetDate(): static
    {
        return $this->state(fn () => [
            'target_date' => null,
        ]);
    }

    /**
     * 指定した受講登録に紐付ける。
     */
    public function forEnrollment(Enrollment $enrollment): static
    {
        return $this->state(fn () => [
            'enrollment_id' => $enrollment->id,
        ]);
    }
}
