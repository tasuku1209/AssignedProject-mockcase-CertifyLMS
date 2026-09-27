<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MeetingStatus;
use App\Exceptions\GoogleCalendar\GoogleOAuthTokenException;
use App\Exceptions\Mentoring\MeetingOutOfAvailabilityException;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\GoogleCredential;
use App\Models\Meeting;
use Carbon\Carbon;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Support\Collection;
use Throwable;

/**
 * 担当コーチ集合の面談可能時間枠を 60 分単位で展開し、
 * Google Calendar の予定および LMS 内の既存予約を除外して空きスロットを集計する Service。
 *
 * 受講生の予約画面が「該当資格の担当コーチ全員の有効枠 Union」を 1 日単位で取得し、
 * 既存予約済時刻と Google Calendar の busy 時間を除外して、
 * 各スロットの「予約可能なコーチ数」を返す。
 *
 * 受講生にコーチ個別は提示せず、予約確定時にコーチを自動割当する。
 *
 * `final` 不採用: `StoreActionTest::test_throws_when_no_coach_is_available` で
 * `Mockery::mock(MeetingAvailabilityService::class)` を使って
 * `validateSlot()` をモックし、コーチ候補が存在しない場合の
 * `MeetingNoAvailableCoachException` を検証するため。
 */
class MeetingAvailabilityService
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService,
    ) {}

    /**
     * 指定 Certification の担当コーチ集合について、
     * 指定日 1 日分の 60 分単位空きスロットを返す。
     *
     * Google Calendar 未連携のコーチは従来どおり LMS の予約状況だけで判定する。
     *
     * Google Calendar 連携済みコーチについて Google 通信に失敗した場合は、
     * Google Calendar による除外を行わず LMS 側の空き枠判定を継続する。
     *
     * @return Collection<int, array{
     *     slot_start: Carbon,
     *     slot_end: Carbon,
     *     available_coach_count: int
     * }>
     */
    public function slotsForCertification(
        Certification $certification,
        Carbon $date,
    ): Collection {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();
        $dayOfWeek = $date->dayOfWeek;

        $coaches = $certification
            ->coaches()
            ->with('googleCredential')
            ->get();

        if ($coaches->isEmpty()) {
            return collect();
        }

        $coachIds = $coaches->pluck('id')->all();

        $availabilities = CoachAvailability::query()
            ->whereIn('coach_id', $coachIds)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get();

        if ($availabilities->isEmpty()) {
            return collect();
        }

        $existingMeetings = Meeting::query()
            ->whereIn('coach_id', $coachIds)
            ->whereBetween('scheduled_at', [$dayStart, $dayEnd])
            ->whereIn('status', [
                MeetingStatus::Reserved->value,
                MeetingStatus::Completed->value,
            ])
            ->get(['coach_id', 'scheduled_at']);

        // 予約済みスロットを (coach_id => [H:i, H:i, ...]) で索引化
        $bookedByCoach = $existingMeetings
            ->groupBy('coach_id')
            ->map(
                fn ($rows) => $rows
                    ->map(
                        fn (Meeting $meeting) => $meeting->scheduled_at->format('H:i')
                    )
                    ->all()
            );

        /*
         * Google Calendar の busy 時間をコーチごとに取得する。
         *
         * Google 未連携のコーチは空配列。
         * Google 通信失敗時も、そのコーチについては空配列として扱い、
         * LMS 側の空き枠判定を継続する。
         *
         * @var array<string, array<int, array{start: Carbon, end: Carbon}>> $busyByCoach
         */
        $busyByCoach = [];

        $availabilityCoachIds = $availabilities
            ->pluck('coach_id')
            ->unique();

        foreach ($availabilityCoachIds as $coachId) {
            $coach = $coaches->firstWhere('id', $coachId);

            if ($coach === null) {
                continue;
            }

            /** @var GoogleCredential|null $credential */
            $credential = $coach->googleCredential;

            if ($credential === null) {
                $busyByCoach[$coachId] = [];

                continue;
            }

            try {
                $busyByCoach[$coachId] = $this->googleCalendarService->getBusyPeriods(
                    $credential,
                    $dayStart,
                    $dayEnd,
                );
            } catch (
                GoogleOAuthTokenException|GoogleServiceException|Throwable $e
            ) {
                /*
                 * Google Calendar の通信に失敗しても、
                 * LMS の面談予約機能自体は継続する。
                 *
                 * Google 側の予定による除外だけを行わない。
                 */
                report($e);

                $busyByCoach[$coachId] = [];
            }
        }

        /** @var array<string, int> $slotCounts スロット開始時刻(H:i) → available coach 数 */
        $slotCounts = [];

        foreach ($availabilities as $availability) {
            $slot = Carbon::parse(
                $date->format('Y-m-d').' '.$availability->start_time
            );

            $end = Carbon::parse(
                $date->format('Y-m-d').' '.$availability->end_time
            );

            while ($slot->copy()->addHour() <= $end) {
                $slotKey = $slot->format('H:i');
                $slotEnd = $slot->copy()->addHour();
                $coachId = $availability->coach_id;

                $booked = $bookedByCoach[$coachId] ?? [];
                $busyPeriods = $busyByCoach[$coachId] ?? [];

                $isBooked = in_array($slotKey, $booked, true);

                $isGoogleBusy = collect($busyPeriods)->contains(
                    fn (array $busy): bool => $busy['start']->lt($slotEnd)
                        && $busy['end']->gt($slot),
                );

                if (! $isBooked && ! $isGoogleBusy) {
                    $slotCounts[$slotKey] = ($slotCounts[$slotKey] ?? 0) + 1;
                }

                $slot->addHour();
            }
        }

        ksort($slotCounts);

        return collect($slotCounts)
            ->map(function (int $count, string $time) use ($date) {
                $start = Carbon::parse(
                    $date->format('Y-m-d').' '.$time
                );

                return [
                    'slot_start' => $start,
                    'slot_end' => $start->copy()->addHour(),
                    'available_coach_count' => $count,
                ];
            })
            ->values();
    }

    /**
     * 指定 scheduled_at が certification 担当コーチ集合の有効枠内かを検証する。
     * 枠外なら MeetingOutOfAvailabilityException を throw する。
     *
     * @throws MeetingOutOfAvailabilityException
     */
    public function validateSlot(
        Certification $certification,
        Carbon $scheduledAt,
    ): void {
        $slots = $this->slotsForCertification(
            $certification,
            $scheduledAt->copy()->startOfDay(),
        );

        $matched = $slots->contains(
            fn (array $slot) => $slot['slot_start']->equalTo($scheduledAt)
                && $slot['available_coach_count'] > 0,
        );

        if (! $matched) {
            throw new MeetingOutOfAvailabilityException;
        }
    }
}
