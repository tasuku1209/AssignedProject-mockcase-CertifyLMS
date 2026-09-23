<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaThread;

use App\Http\Requests\QaThread\UpdateRequest;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 質問掲示板スレッド更新 UpdateRequest の rules() バリデーション検証。
 * title / body の必須・文字列・最大文字数を検証する。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_payload(): void
    {
        // Arrange
        $payload = [
            'title' => 'Laravelについて質問があります',
            'body' => 'Laravelの認証処理について教えてください。',
        ];

        // Act
        $validator = Validator::make(
            $payload,
            (new UpdateRequest)->rules()
        );

        // Assert
        $this->assertTrue(
            $validator->passes(),
            $validator->errors()->toJson()
        );
    }

    #[DataProvider('invalidCases')]
    public function test_fails_for_invalid_field(
        string $field,
        mixed $value
    ): void {
        // Arrange
        $payload = array_merge(
            [
                'title' => 'Laravelについて質問があります',
                'body' => 'Laravelの認証処理について教えてください。',
            ],
            [$field => $value]
        );

        // Act
        $validator = Validator::make(
            $payload,
            (new UpdateRequest)->rules()
        );

        // Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey(
            $field,
            $validator->errors()->toArray()
        );
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function invalidCases(): array
    {
        return [
            'title 未指定で エラー' => ['title', ''],
            'title 201 文字で エラー' => ['title', str_repeat('a', 201)],
            'body 未指定で エラー' => ['body', ''],
            'body 5001 文字で エラー' => ['body', str_repeat('b', 5001)],
        ];
    }

    public function test_authorize_returns_false_when_student_updates_another_students_thread(): void
    {
        // Arrange
        $owner = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $thread = QaThread::factory()
            ->for($owner, 'user')
            ->create();

        $request = UpdateRequest::create(
            route('qa-board.update', $thread),
            'PATCH',
        );

        $request->setUserResolver(fn () => $otherStudent);

        $request->setRouteResolver(
            fn () => new class($thread)
            {
                public function __construct(
                    private QaThread $thread,
                ) {}

                public function parameter(string $key): ?QaThread
                {
                    return $key === 'thread'
                        ? $this->thread
                        : null;
                }
            }
        );

        // Act / Assert
        $this->assertFalse($request->authorize());
    }
}
