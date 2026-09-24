<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Enums\UserStatus;
use App\Exceptions\GoogleCalendar\GoogleOAuthTokenException;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Exceptions\Mentoring\MeetingNoAvailableCoachException;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReservedNotification;
use App\Services\CoachMeetingLoadService;
use App\Services\GoogleCalendarService;
use App\Services\MeetingAvailabilityService;
use App\Services\MeetingQuotaService;
use App\UseCases\MeetingQuota\ConsumeQuotaAction;
use Carbon\Carbon;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class StoreAction
{
    public function __construct(
        private readonly MeetingAvailabilityService $availabilityService,
        private readonly CoachMeetingLoadService $coachLoadService,
        private readonly MeetingQuotaService $quotaService,
        private readonly ConsumeQuotaAction $consumeAction,
        private readonly GoogleCalendarService $googleCalendarService,
    ) {}

    public function __invoke(
        Enrollment $enrollment,
        User $student,
        Carbon $scheduledAt,
        ?string $topic,
    ): Meeting {
        $meeting = DB::transaction(function () use (
            $enrollment,
            $student,
            $scheduledAt,
            $topic,
        ) {
            if ($this->quotaService->remaining($student) < 1) {
                throw new InsufficientMeetingQuotaException;
            }

            $this->availabilityService->validateSlot(
                $enrollment->certification,
                $scheduledAt,
            );

            $candidates = $this->findAvailableCoaches(
                $enrollment->certification,
                $scheduledAt,
            );

            if ($candidates->isEmpty()) {
                throw new MeetingNoAvailableCoachException;
            }

            $coach = $this->coachLoadService->leastLoadedCoach($candidates);

            try {
                $meeting = Meeting::create([
                    'enrollment_id' => $enrollment->id,
                    'coach_id' => $coach->id,
                    'student_id' => $student->id,
                    'scheduled_at' => $scheduledAt,
                    'status' => MeetingStatus::Reserved->value,
                    'topic' => $topic,
                    'meeting_url_snapshot' => $coach->meeting_url,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                throw new MeetingNoAvailableCoachException($e);
            }

            $transaction = ($this->consumeAction)($student, $meeting->id);

            $meeting->update([
                'meeting_quota_transaction_id' => $transaction->id,
            ]);

            DB::afterCommit(function () use ($meeting): void {
                $coach = $meeting->coach;

                if ($coach->status === UserStatus::InProgress) {
                    $coach->notify(new MeetingReservedNotification($meeting));
                }
            });

            return $meeting->fresh();
        });

        $meeting->loadMissing('coach.googleCredential');

        $credential = $meeting->coach->googleCredential;

        if ($credential !== null) {
            try {
                $googleEventId = $this->googleCalendarService->createEvent(
                    credential: $credential,
                    summary: '面談：'.$meeting->student->name,
                    start: $meeting->scheduled_at,
                    end: $meeting->scheduled_at->copy()->addHour(),
                    meetingUrl: $meeting->meeting_url_snapshot,
                );

                $meeting->update([
                    'google_event_id' => $googleEventId,
                ]);
            } catch (GoogleOAuthTokenException|GoogleServiceException $e) {
                report($e);
            }
        }

        return $meeting;
    }

    /**
     * 担当コーチ集合のうち、(1) 当該時刻に有効な availability 枠があり、
     * (2) 当該時刻に reserved / completed の Meeting を持たないコーチ集合を返す。
     *
     * @return Collection<int, User>
     */
    private function findAvailableCoaches(
        Certification $certification,
        Carbon $scheduledAt,
    ): Collection {
        $time = $scheduledAt->format('H:i:s');

        return $certification->coaches()
            ->whereHas('coachAvailabilities', function ($q) use ($scheduledAt, $time) {
                $q->where('day_of_week', $scheduledAt->dayOfWeek)
                    ->where('is_active', true)
                    ->where('start_time', '<=', $time)
                    ->where('end_time', '>', $time);
            })
            ->whereDoesntHave('meetingsAsCoach', function ($q) use ($scheduledAt) {
                $q->where('scheduled_at', $scheduledAt)
                    ->whereIn('status', [
                        MeetingStatus::Reserved->value,
                        MeetingStatus::Completed->value,
                    ]);
            })
            ->get();
    }
}
