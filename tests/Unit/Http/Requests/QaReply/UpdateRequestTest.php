<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaReply;

use App\Http\Requests\QaReply\UpdateRequest;
use App\Models\QaReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 質問掲示板 回答更新 UpdateRequest のバリデーション・認可を検証する。
 *
 * body の必須・文字数と、
 * QaReplyPolicy::update による自分の回答に対する認可を検証する。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_valid_payload(): void
    {
        // Arrange
        $payload = [
            'body' => '回答内容を更新しました。',
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
        $payload = [
            'body' => '回答内容を更新しました。',
        ];

        $payload[$field] = $value;

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
            'body 未指定で エラー' => ['body', ''],
            'body 5001 文字で エラー' => ['body', str_repeat('a', 5001)],
        ];
    }

    public function test_authorize_returns_true_for_student_own_reply(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->create();

        $reply = QaReply::factory()
            ->forUser($student)
            ->create();

        $request = UpdateRequest::create(
            route('qa-board.replies.update', [
                'thread' => $reply->qa_thread_id,
                'reply' => $reply->id,
            ]),
            'PATCH'
        );

        $route = app('router')
            ->getRoutes()
            ->match($request);

        $route->setParameter('reply', $reply);

        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $student);

        // Assert
        $this->assertTrue($request->authorize());
    }

    public function test_authorize_returns_true_for_coach_own_reply(): void
    {
        // Arrange
        $coach = User::factory()
            ->coach()
            ->create();

        $reply = QaReply::factory()
            ->forUser($coach)
            ->create();

        $request = UpdateRequest::create(
            route('qa-board.replies.update', [
                'thread' => $reply->qa_thread_id,
                'reply' => $reply->id,
            ]),
            'PATCH'
        );

        $route = app('router')
            ->getRoutes()
            ->match($request);

        $route->setParameter('reply', $reply);

        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $coach);

        // Assert
        $this->assertTrue($request->authorize());
    }

    public function test_authorize_returns_false_for_other_users_reply(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->create();

        $reply = QaReply::factory()
            ->forUser($otherStudent)
            ->create();

        $request = UpdateRequest::create(
            route('qa-board.replies.update', [
                'thread' => $reply->qa_thread_id,
                'reply' => $reply->id,
            ]),
            'PATCH'
        );

        $route = app('router')
            ->getRoutes()
            ->match($request);

        $route->setParameter('reply', $reply);

        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $student);

        // Assert
        $this->assertFalse($request->authorize());
    }

    public function test_authorize_returns_false_for_admin(): void
    {
        // Arrange
        $admin = User::factory()
            ->admin()
            ->create();

        $student = User::factory()
            ->student()
            ->create();

        $reply = QaReply::factory()
            ->forUser($student)
            ->create();

        $request = UpdateRequest::create(
            route('qa-board.replies.update', [
                'thread' => $reply->qa_thread_id,
                'reply' => $reply->id,
            ]),
            'PATCH'
        );

        $route = app('router')
            ->getRoutes()
            ->match($request);

        $route->setParameter('reply', $reply);

        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $admin);

        // Assert
        $this->assertFalse($request->authorize());
    }
}
