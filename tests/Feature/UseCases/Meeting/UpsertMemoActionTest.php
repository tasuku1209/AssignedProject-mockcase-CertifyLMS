<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\MeetingMemo;
use App\UseCases\Meeting\UpsertMemoAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpsertMemoActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_throws_when_meeting_status_does_not_allow_memo(): void
    {
        // Arrange
        $meeting = Meeting::factory()
            ->canceled()
            ->create();

        $action = app(UpsertMemoAction::class);

        // Act & Assert
        $this->expectException(MeetingStatusTransitionException::class);

        $action($meeting, 'キャンセル済み面談のメモ');
    }

    public function test_creates_memo_for_reserved_meeting(): void
    {
        // Arrange
        $meeting = Meeting::factory()
            ->reserved()
            ->create();

        $action = app(UpsertMemoAction::class);

        // Act
        $memo = $action($meeting, '予約済み面談のメモ');

        // Assert
        $this->assertInstanceOf(MeetingMemo::class, $memo);
        $this->assertDatabaseHas('meeting_memos', [
            'meeting_id' => $meeting->id,
            'body' => '予約済み面談のメモ',
        ]);
    }

    public function test_creates_memo_for_completed_meeting(): void
    {
        // Arrange
        $meeting = Meeting::factory()
            ->completed()
            ->create();

        $action = app(UpsertMemoAction::class);

        // Act
        $memo = $action($meeting, '完了済み面談のメモ');

        // Assert
        $this->assertInstanceOf(MeetingMemo::class, $memo);
        $this->assertDatabaseHas('meeting_memos', [
            'meeting_id' => $meeting->id,
            'body' => '完了済み面談のメモ',
        ]);
    }

    public function test_updates_existing_memo(): void
    {
        // Arrange
        $meeting = Meeting::factory()
            ->completed()
            ->create();

        $existingMemo = MeetingMemo::factory()
            ->forMeeting($meeting)
            ->create([
                'body' => '初回面談メモ',
            ]);

        $action = app(UpsertMemoAction::class);

        // Act
        $memo = $action($meeting, '更新後の面談メモ');

        // Assert
        $this->assertTrue($memo->is($existingMemo));
        $this->assertSame('更新後の面談メモ', $memo->body);

        $this->assertDatabaseHas('meeting_memos', [
            'meeting_id' => $meeting->id,
            'body' => '更新後の面談メモ',
        ]);

        $this->assertDatabaseCount('meeting_memos', 1);
    }
}
