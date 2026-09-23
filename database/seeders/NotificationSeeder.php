<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use App\Notifications\MeetingCanceledNotification;
use App\Notifications\MeetingReservedNotification;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

final class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        $coach1 = User::query()
            ->where('email', 'coach@certify-lms.test')
            ->first();

        $coach2 = User::query()
            ->where('email', 'coach2@certify-lms.test')
            ->first();

        if ($student === null || $coach1 === null || $coach2 === null) {
            $this->command?->warn(
                'NotificationSeeder: 固定の受講生・コーチが存在しません。先に UserSeeder を実行してください。'
            );

            return;
        }

        $enrollment = $student->enrollments()
            ->whereHas(
                'certification',
                fn ($query) => $query->where('name', 'TOEIC L&R 800 点コース')
            )
            ->first();

        if ($enrollment === null) {
            $this->command?->warn(
                'NotificationSeeder: 固定受講生の TOEIC Enrollment が存在しません。先に EnrollmentSeeder を実行してください。'
            );

            return;
        }

        $room = ChatRoom::query()
            ->where('enrollment_id', $enrollment->id)
            ->first();

        if ($room === null) {
            $this->command?->warn(
                'NotificationSeeder: TOEIC の ChatRoom が存在しません。先に ChatSeeder を実行してください。'
            );

            return;
        }

        $this->seedQaReplyNotifications($student);
        $this->seedChatNotifications($student, $coach1, $coach2, $room);
        $this->seedMeetingNotifications($student, $coach1, $coach2, $enrollment);

        DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $student->id)
            ->latest('created_at')
            ->limit(5)
            ->update(['read_at' => null]);
    }

    /**
     * 固定受講生の質問10件に、各1件ずつ担当コーチの回答を作成する。
     */
    private function seedQaReplyNotifications(User $student): void
    {
        $threads = QaThread::query()
            ->where('user_id', $student->id)
            ->with('certification.coaches')
            ->orderBy('created_at')
            ->get();

        if ($threads->isEmpty()) {
            $this->command?->warn(
                'NotificationSeeder: 固定受講生の QaThread が存在しません。先に QaThreadSeeder を実行してください。'
            );

            return;
        }

        foreach ($threads as $index => $thread) {
            $coach = $thread->certification->coaches->first();

            if ($coach === null) {
                $this->command?->warn(
                    "NotificationSeeder: QaThread {$thread->id} に担当コーチが存在しないため、回答をスキップしました。"
                );

                continue;
            }

            $reply = QaReply::factory()
                ->forThread($thread)
                ->forUser($coach)
                ->create();

            $this->storeDatabaseNotification(
                $student,
                new QaReplyReceivedNotification($reply),
                true,
            );
        }
    }

    /**
     * チャット通知を投入する。
     *
     * 受講生 → コーチ1 : 1件
     * コーチ1 → 受講生 : 3件
     * コーチ2 → 受講生 : 3件
     */
    private function seedChatNotifications(
        User $student,
        User $coach1,
        User $coach2,
        ChatRoom $room,
    ): void {
        $messages = [
            [$student, 1],
            [$coach1, 3],
            [$coach2, 3],
        ];

        foreach ($messages as [$sender, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $message = ChatMessage::factory()->create([
                    'chat_room_id' => $room->id,
                    'sender_user_id' => $sender->id,
                ]);

                $receivers = ChatMember::query()
                    ->where('chat_room_id', $room->id)
                    ->where('user_id', '!=', $sender->id)
                    ->with('user')
                    ->get();

                foreach ($receivers as $receiver) {
                    $this->storeDatabaseNotification(
                        $receiver->user,
                        new ChatMessageReceivedNotification($message),
                        true,
                    );
                }
            }
        }
    }

    /**
     * 面談予約・キャンセル通知を投入する。
     *
     * 予約:
     *   受講生 → コーチ1 : 1件
     *
     * キャンセル:
     *   受講生 → コーチ1 : 1件
     *   コーチ1 → 受講生 : 3件
     *   コーチ2 → 受講生 : 3件
     */
    private function seedMeetingNotifications(
        User $student,
        User $coach1,
        User $coach2,
        Enrollment $enrollment,
    ): void {
        $reservedMeeting = Meeting::factory()
            ->reserved()
            ->forEnrollment($enrollment)
            ->forStudent($student)
            ->forCoach($coach1)
            ->create();

        $this->storeDatabaseNotification(
            $coach1,
            new MeetingReservedNotification($reservedMeeting),
            false,
        );

        $studentCanceledMeeting = Meeting::factory()
            ->canceled()
            ->forEnrollment($enrollment)
            ->forStudent($student)
            ->forCoach($coach1)
            ->create([
                'canceled_by_user_id' => $student->id,
            ]);

        $this->storeDatabaseNotification(
            $coach1,
            new MeetingCanceledNotification($studentCanceledMeeting),
            true,
        );

        for ($i = 0; $i < 3; $i++) {
            $meeting = Meeting::factory()
                ->canceled()
                ->forEnrollment($enrollment)
                ->forStudent($student)
                ->forCoach($coach1)
                ->create([
                    'canceled_by_user_id' => $coach1->id,
                ]);

            $this->storeDatabaseNotification(
                $student,
                new MeetingCanceledNotification($meeting),
                true,
            );
        }

        for ($i = 0; $i < 3; $i++) {
            $meeting = Meeting::factory()
                ->canceled()
                ->forEnrollment($enrollment)
                ->forStudent($student)
                ->forCoach($coach2)
                ->create([
                    'canceled_by_user_id' => $coach2->id,
                ]);

            $this->storeDatabaseNotification(
                $student,
                new MeetingCanceledNotification($meeting),
                true,
            );
        }
    }

    /**
     * Notification の toArray() を利用して Database Notification を投入する。
     *
     * @param bool $read 既読なら true、未読なら false
     */
    private function storeDatabaseNotification(
        User $notifiable,
        object $notification,
        bool $read,
    ): void {
        $createdAt = now()->subDays(fake()->numberBetween(1, 10));

        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => $notification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $notifiable->id,
            'data' => $notification->toArray($notifiable),
            'read_at' => $read ? $createdAt : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
