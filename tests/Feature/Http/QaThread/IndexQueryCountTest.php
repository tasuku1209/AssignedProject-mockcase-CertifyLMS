<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 質問掲示板一覧の N+1 非回帰を検証する Feature テスト。
 *
 * 投稿者・資格を Eager Load、回答数を withCount で一括取得することで、
 * スレッド件数を増やしても発行クエリ数がほぼ増えないことを担保する。
 */
class IndexQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_query_count_does_not_grow_with_thread_count(): void
    {
        // Arrange: 受講生 + 共通資格に紐づくスレッド 2 件（基準）
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $this->createThreads($student, $certification, 2);

        // Act: 基準のクエリ数を計測
        $baseline = $this->countQueriesFor(
            fn () => $this->actingAs($student)->get(route('qa-board.index'))
        );

        // スレッドを 10 件追加して再計測（1ページに収まる件数）
        $this->createThreads($student, $certification, 10);

        $scaled = $this->countQueriesFor(
            fn () => $this->actingAs($student)->get(route('qa-board.index'))
        );

        // Assert: スレッドが増えても発行クエリ数はほぼ一定
        // N+1 が発生していれば、スレッド件数に応じてクエリ数が増加する
        $this->assertLessThanOrEqual(
            $baseline + 3,
            $scaled,
            "質問掲示板一覧で N+1 が再発している (基準 {$baseline} → 増加後 {$scaled})。投稿者 / 資格を Eager Loading、回答数を withCount で一括取得しているか確認",
        );
    }

    private function createThreads(
        User $student,
        Certification $certification,
        int $count,
    ): void {
        QaThread::factory()
            ->count($count)
            ->for($student, 'user')
            ->for($certification, 'certification')
            ->create();
    }

    private function countQueriesFor(\Closure $closure): int
    {
        $count = 0;

        DB::listen(function () use (&$count): void {
            $count++;
        });

        $closure();

        return $count;
    }
}
