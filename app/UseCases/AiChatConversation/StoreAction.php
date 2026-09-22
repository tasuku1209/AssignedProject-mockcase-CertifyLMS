<?php

declare(strict_types=1);

namespace App\UseCases\AiChatConversation;

use App\Enums\EnrollmentStatus;
use App\Models\AiChatConversation;
use App\Models\Section;
use App\Models\User;
use App\UseCases\AiChatMessage\StoreAction as StoreMessageAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StoreAction
{
    public function __construct(
        private readonly StoreMessageAction $storeMessageAction,
    ) {}

    /**
     * AIチャット相談を取得または新規作成する。
     *
     * Sectionが指定された場合は、そのSectionが属するCertificationについて
     * ログイン中ユーザーのLearning状態のEnrollmentを取得する。
     *
     * 同じユーザー・Enrollment・SectionのConversationが既に存在する場合は、
     * 既存Conversationを再利用する。
     *
     * Sectionが指定されない場合は、常に新しいConversationを作成する。
     *
     * 初回messageが指定された場合は、Conversation取得・作成後に
     * Message StoreActionへ処理を委譲する。
     *
     * @param array{
     *     source: string,
     *     section_id?: ?string,
     *     message?: ?string
     * } $validated
     *
     * @return array{
     *     conversation: AiChatConversation,
     *     created: bool
     * }
     */
    public function __invoke(
        User $user,
        array $validated,
    ): array {
        $enrollment = null;
        $sectionId = $validated['section_id'] ?? null;

        if ($sectionId !== null) {
            $section = Section::query()
                ->with('chapter.part.certification')
                ->find($sectionId);

            if ($section === null) {
                throw ValidationException::withMessages([
                    'section_id' => '指定されたSectionが存在しません。',
                ]);
            }

            $certification = $section->chapter?->part?->certification;

            if ($certification === null) {
                throw ValidationException::withMessages([
                    'section_id' => '指定されたSectionの資格情報を取得できません。',
                ]);
            }

            $enrollment = $user->enrollments()
                ->where('certification_id', $certification->id)
                ->where(
                    'status',
                    EnrollmentStatus::Learning->value,
                )
                ->first();

            if ($enrollment === null) {
                throw ValidationException::withMessages([
                    'section_id' => 'このSectionは現在受講中の資格に属していません。',
                ]);
            }
        } else {
            $enrollment = $user->defaultEnrollment()
                ->where(
                    'status',
                    EnrollmentStatus::Learning->value,
                )
                ->first();
        }

        $result = DB::transaction(
            function () use (
                $user,
                $enrollment,
                $sectionId,
            ): array {
                if ($sectionId !== null) {
                    $existingConversation = AiChatConversation::query()
                        ->where('user_id', $user->id)
                        ->where('enrollment_id', $enrollment->id)
                        ->where('section_id', $sectionId)
                        ->first();

                    if ($existingConversation !== null) {
                        return [
                            'conversation' => $existingConversation,
                            'created' => false,
                        ];
                    }
                }

                $conversation = AiChatConversation::create([
                    'user_id' => $user->id,
                    'enrollment_id' => $enrollment?->id,
                    'section_id' => $sectionId,
                    'title' => config('ai-chat.auto_title.enabled')
                        ? null
                        : '新規相談',
                    'auto_title_enabled' => config('ai-chat.auto_title.enabled'),
                    'last_message_at' => null,
                ]);

                return [
                    'conversation' => $conversation,
                    'created' => true,
                ];
            },
        );

        $conversation = $result['conversation'];

        $message = $validated['message'] ?? null;

        if ($message !== null && $message !== '') {
            ($this->storeMessageAction)(
                $conversation,
                $user,
                ['content' => $message],
            );
        }

        return [
            'conversation' => $conversation->fresh(),
            'created' => $result['created'],
        ];
    }
}
