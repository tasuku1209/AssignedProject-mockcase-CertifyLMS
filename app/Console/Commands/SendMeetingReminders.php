<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MeetingStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use Illuminate\Console\Command;

class SendMeetingReminders extends Command
{
    protected $signature = 'notifications:send-meeting-reminders
                            {--window= : リマインドのタイミング（eve / one_hour_before）}';

    protected $description = '面談のリマインド通知を送信します';

    public function handle(): int
    {
        $window = $this->option('window');

        if (! in_array($window, ['eve', 'one_hour_before'], true)) {
            $this->error('--windowには eve または one_hour_before を指定してください。');

            return self::FAILURE;
        }

        $meetings = $this->targetMeetings($window);

        foreach ($meetings as $meeting) {
            $this->sendReminder($meeting, $window);
        }

        $this->info("{$meetings->count()}件の対象面談を確認しました。");

        return self::SUCCESS;
    }

    private function targetMeetings(string $window)
    {
        return match ($window) {
            'eve' => Meeting::query()
                ->where('status', MeetingStatus::Reserved)
                ->whereBetween('scheduled_at', [
                    now()->addDay()->startOfDay(),
                    now()->addDay()->endOfDay(),
                ])
                ->with(['student', 'coach'])
                ->get(),

            'one_hour_before' => Meeting::query()
                ->where('status', MeetingStatus::Reserved)
                ->whereBetween('scheduled_at', [
                    now()->addHour()->startOfHour(),
                    now()->addHour()->endOfHour(),
                ])
                ->with(['student', 'coach'])
                ->get(),
        };
    }

    private function sendReminder(Meeting $meeting, string $window): void
    {
        $recipients = collect([
            $meeting->student,
            $meeting->coach,
        ])->filter(
            fn (User $user): bool => match ($user->role) {
                UserRole::Student => in_array(
                    $user->status,
                    [UserStatus::InProgress, UserStatus::Graduated],
                    true,
                ),
                UserRole::Coach => $user->status === UserStatus::InProgress,
                default => false,
            }
        );

        foreach ($recipients as $recipient) {
            if ($this->alreadySent($meeting, $recipient, $window)) {
                continue;
            }

            $recipient->notify(
                new MeetingReminderNotification($meeting, $window)
            );
        }
    }

    private function alreadySent(
        Meeting $meeting,
        User $recipient,
        string $window,
    ): bool {
        return $recipient->notifications()
            ->where('type', MeetingReminderNotification::class)
            ->get()
            ->contains(
                fn ($notification): bool => ($notification->data['meeting_id'] ?? null) === $meeting->id
                    && ($notification->data['reminder_window'] ?? null) === $window
            );
    }
}
