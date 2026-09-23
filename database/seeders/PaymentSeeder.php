<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * 決済 Feature のデモデータシーダー。
 *
 * **設計思想**:
 *
 * - 固定 student には pending / failed / succeeded の決済履歴を投入し、
 *   決済状態ごとの履歴表示を確認できる状態を作る。
 * - 固定 student の succeeded は 5 回パック + 10 回パックとし、
 *   合計 15 回分の面談回数を購入分として残数に反映する。
 * - demo の learning 受講生には全員、pending / failed / succeeded の
 *   決済履歴を投入し、succeeded の 1 回分だけ残数に反映する。
 * - Payment の succeeded 時のみ MeetingQuotaTransaction(Purchased) を作成し、
 *   pending / failed は面談回数に影響させない。
 * - student-noquota@certify-lms.test は残数 0 の動作確認用アカウントのため、
 *   決済データを投入しない。
 *
 * 依存: UserSeeder → MeetingPackSeeder の後に走る前提
 * (`users` と `meeting_packs` を参照する)。
 */
final class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $studentExists = User::query()
            ->where('email', 'student@certify-lms.test')
            ->exists();

        $demoStudentExists = User::query()
            ->where('role', 'student')
            ->where('status', 'in_progress')
            ->whereNotIn('email', [
                'student@certify-lms.test',
                'student-noquota@certify-lms.test',
            ])
            ->exists();

        if (! $studentExists && ! $demoStudentExists) {
            $this->command?->warn(
                'PaymentSeeder: 受講生 User が存在しません。先に UserSeeder を実行してください。'
            );

            return;
        }

        $packs = MeetingPack::query()
            ->whereIn('name', [
                '1 回パック',
                '5 回パック',
                '10 回パック',
            ])
            ->get()
            ->keyBy('name');

        $requiredPackNames = [
            '1 回パック',
            '5 回パック',
            '10 回パック',
        ];

        foreach ($requiredPackNames as $name) {
            if (! $packs->has($name)) {
                $this->command?->warn(
                    "PaymentSeeder: {$name} が存在しません。先に MeetingPackSeeder を実行してください。"
                );

                return;
            }
        }

        $fixedStudent = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        if ($fixedStudent !== null) {
            $this->seedFixedStudentPayments($fixedStudent, $packs);
        }

        $demoStudents = User::query()
            ->where('role', 'student')
            ->where('status', 'in_progress')
            ->whereNotIn('email', [
                'student@certify-lms.test',
                'student-noquota@certify-lms.test',
            ])
            ->orderBy('created_at')
            ->get();

        foreach ($demoStudents as $student) {
            $this->seedDemoStudentPayments($student, $packs);
        }
    }

    /**
     * 固定受講生には決済状態の異なる購入履歴を投入する。
     *
     * - pending   : 1回パック
     * - failed    : 1回パック
     * - succeeded : 5回パック
     * - succeeded : 10回パック
     *
     * 成功した購入分のみ面談回数に反映する。
     *
     * @param Collection<string, MeetingPack> $packs
     */
    private function seedFixedStudentPayments(
        User $student,
        Collection $packs,
    ): void {
        $this->createPayment(
            $student,
            $packs['1 回パック'],
            PaymentStatus::Pending,
        );

        $this->createPayment(
            $student,
            $packs['1 回パック'],
            PaymentStatus::Failed,
        );

        $this->createPayment(
            $student,
            $packs['5 回パック'],
            PaymentStatus::Succeeded,
        );

        $this->createPayment(
            $student,
            $packs['10 回パック'],
            PaymentStatus::Succeeded,
        );
    }

    /**
     * デモ受講生には全員同じ決済履歴を投入する。
     *
     * - pending   : 1回パック
     * - failed    : 1回パック
     * - succeeded : 1回パック
     *
     * 成功した1回分のみ面談回数に反映する。
     *
     * @param Collection<string, MeetingPack> $packs
     */
    private function seedDemoStudentPayments(
        User $student,
        Collection $packs,
    ): void {
        $this->createPayment(
            $student,
            $packs['1 回パック'],
            PaymentStatus::Pending,
        );

        $this->createPayment(
            $student,
            $packs['1 回パック'],
            PaymentStatus::Failed,
        );

        $this->createPayment(
            $student,
            $packs['1 回パック'],
            PaymentStatus::Succeeded,
        );
    }

    /**
     * Paymentを作成し、成功時のみ購入トランザクションを作成する。
     */
    private function createPayment(
        User $student,
        MeetingPack $meetingPack,
        PaymentStatus $status,
    ): Payment {
        $payment = Payment::factory()
            ->forMeetingPack($meetingPack)
            ->state([
                'status' => $status->value,
                'paid_at' => $status === PaymentStatus::Succeeded
                    ? now()
                    : null,
            ])
            ->create([
                'user_id' => $student->id,
            ]);

        if ($status === PaymentStatus::Succeeded) {
            MeetingQuotaTransaction::create([
                'user_id' => $student->id,
                'type' => MeetingQuotaTransactionType::Purchased->value,
                'amount' => $payment->quantity,
                'related_payment_id' => $payment->id,
                'occurred_at' => $payment->paid_at,
            ]);
        }

        return $payment;
    }
}
