<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Enrollment;
use App\Services\MeetingAvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class FetchAvailabilityAction
{
    public function __construct(
        private readonly MeetingAvailabilityService $availabilityService,
    ) {}

    /**
     * 指定された受講の認定資格について、指定日の空き枠を取得する。
     *
     * @return Collection<int, array{
     *     slot_start: Carbon,
     *     slot_end: Carbon,
     *     available_coach_count: int
     * }>
     */
    public function __invoke(
        Enrollment $enrollment,
        Carbon $date,
    ): Collection {
        return $this->availabilityService->slotsForCertification(
            $enrollment->loadMissing('certification')->certification,
            $date,
        );
    }
}
