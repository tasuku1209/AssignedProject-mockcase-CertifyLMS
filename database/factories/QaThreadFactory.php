<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QaThread>
 */
class QaThreadFactory extends Factory
{
    protected $model = QaThread::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'certification_id' => Certification::factory()->published(),
            'title' => fake()->randomElement([
                'Aこの問題の解き方が分かりません',
                'Bこの問題について質問があります',
                'C教材のこの部分がよく分かりません',
                'D効率的な勉強方法を教えてください',
                'Eこの用語について教えてください',
                'F模擬試験で間違えてしまいました',
                'Gこの問題の考え方を教えてください',
                'H試験対策について質問です',
                'Iこの範囲の勉強方法について相談です',
                'J教材の内容について確認したいです',
            ]),
            'body' => fake()->randomElement([
                '1教材を読んでいるのですが、この部分の考え方がよく分かりません。どのように理解すればよいでしょうか。',
                '2自分なりに考えてみたのですが、なぜこの答えになるのか理解できません。考え方を教えていただきたいです。',
                '3模擬試験で同じような問題を何度か間違えてしまいます。効率的な勉強方法があれば教えてください。',
                '4この範囲を勉強していますが、どこを重点的に復習すればよいのか迷っています。おすすめの学習方法があれば知りたいです。',
                '5教材に書かれている内容について確認したいことがあります。実際の試験ではどの程度まで理解しておけばよいでしょうか。',
                '6問題文の意味は理解できるのですが、解答にたどり着くまでの考え方が分かりません。詳しく教えていただけると助かります。',
                '7この用語について調べてみましたが、教材の説明だけでは理解しきれませんでした。具体例を含めて教えていただけますか。',
                '8学習を進めている中で疑問が出てきました。同じようなところでつまずいた方がいれば、どのように解決したか教えてください。',
            ]),
            'status' => QaThreadStatus::Unresolved->value,
            'resolved_at' => null,
        ];
    }

    /**
     * 解決済みの質問。
     */
    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => QaThreadStatus::Resolved->value,
            'resolved_at' => now(),
        ]);
    }

    /**
     * 未解決の質問。
     */
    public function unresolved(): static
    {
        return $this->state(fn () => [
            'status' => QaThreadStatus::Unresolved->value,
            'resolved_at' => null,
        ]);
    }

    /**
     * 指定した資格に紐づける。
     */
    public function forCertification(Certification $certification): static
    {
        return $this->state(fn () => [
            'certification_id' => $certification->id,
        ]);
    }

    /**
     * 指定した投稿者に紐づける。
     */
    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
        ]);
    }
}
