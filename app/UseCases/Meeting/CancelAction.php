<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\GoogleCalendar\GoogleOAuthTokenException;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingCanceledNotification;
use App\Services\GoogleCalendarService;
use App\UseCases\MeetingQuota\RefundQuotaAction;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Support\Facades\DB;

final class CancelAction
{
    public function __construct(
        private readonly RefundQuotaAction $refundAction,
        private readonly GoogleCalendarService $googleCalendarService,
    ) {}

    public function __invoke(
        Meeting $meeting,
        User $actor,
    ): Meeting {
        DB::transaction(function () use ($meeting, $actor) {
            $locked = Meeting::query()
                ->whereKey($meeting->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->status !== MeetingStatus::Reserved) {
                throw MeetingStatusTransitionException::forCancel();
            }

            if ($locked->scheduled_at->lessThanOrEqualTo(now())) {
                throw new MeetingAlreadyStartedException;
            }

            $locked->update([
                'status' => MeetingStatus::Canceled->value,
                'canceled_by_user_id' => $actor->id,
                'canceled_at' => now(),
            ]);

            ($this->refundAction)($locked->student, $locked->id);

            DB::afterCommit(function () use ($locked, $actor): void {
                $recipient = $locked->student_id === $actor->id
                    ? $locked->coach
                    : $locked->student;

                $shouldNotify = match ($recipient->role) {
                    UserRole::Student => in_array(
                        $recipient->status,
                        [
                            UserStatus::InProgress,
                            UserStatus::Graduated,
                        ],
                        true,
                    ),
                    UserRole::Coach => $recipient->status === UserStatus::InProgress,
                    default => false,
                };

                if ($shouldNotify) {
                    $recipient->notify(
                        new MeetingCanceledNotification($locked)
                    );
                }
            });
        });

        $meeting->loadMissing('coach.googleCredential');

        $credential = $meeting->coach->googleCredential;

        if (
            $credential !== null
            && $meeting->google_event_id !== null
        ) {
            try {
                $this->googleCalendarService->deleteEvent(
                    credential: $credential,
                    eventId: $meeting->google_event_id,
                );
            } catch (GoogleOAuthTokenException|GoogleServiceException $e) {
                report($e);
            }
        }

        return $meeting;
    }
}
