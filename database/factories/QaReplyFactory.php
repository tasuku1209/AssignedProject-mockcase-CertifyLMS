<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QaReply>
 */
class QaReplyFactory extends Factory
{
    protected $model = QaReply::class;

    public function definition(): array
    {
        return [
            'qa_thread_id' => QaThread::factory(),
            'user_id' => User::factory()->student(),
            'body' => fake()->randomElement([
                'ご質問の内容については、まず教材のこの部分を確認してみると分かりやすいと思います。',
                '私も最初は同じところで迷いました。問題文の条件を一つずつ整理すると解きやすくなります。',
                'この問題では、まず基本的な考え方を理解してから問題を解いてみるのがおすすめです。',
                '教材の説明だけでは分かりにくい部分だと思います。具体例を使って考えると理解しやすくなります。',
                '同じような問題を何度か解いてみると、解き方のパターンが分かってくると思います。',
                'この範囲は試験でもよく出題されるので、間違えた問題を中心に復習しておくとよいと思います。',
                '私の場合は、問題を解いたあとに間違えた理由を書き出すことで理解しやすくなりました。',
                '質問の内容からすると、この部分をもう少し詳しく確認すると解決できるかもしれません。',
            ]),
        ];
    }

    /**
     * 指定した質問スレッドへの回答にする。
     */
    public function forThread(QaThread $thread): static
    {
        return $this->state(fn () => [
            'qa_thread_id' => $thread->id,
        ]);
    }

    /**
     * 指定したユーザーを回答者にする。
     */
    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
        ]);
    }
}
