<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaReply;

use App\Http\Requests\QaReply\StoreRequest;
use App\Models\CertificationCoachAssignment;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 質問掲示板 回答登録 StoreRequest のバリデーション・認可を検証する。
 *
 * body の必須・文字数と、
 * QaReplyPolicy::create による受講生・コーチの認可を検証する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_valid_payload(): void
    {
        // Arrange
        $payload = [
            'body' => 'こちらの手順で確認してみてください。',
        ];

        // Act
        $validator = Validator::make(
            $payload,
            (new StoreRequest)->rules()
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
            'body' => 'こちらの手順で確認してみてください。',
        ];

        $payload[$field] = $value;

        // Act
        $validator = Validator::make(
            $payload,
            (new StoreRequest)->rules()
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

    public function test_authorize_returns_true_for_student(): void
    {
        // Arrange
        $student = User::factory()
            ->student()
            ->create();

        $thread = QaThread::factory()->create();

        $request = StoreRequest::create(
            route('qa-board.replies.store', $thread),
            'POST'
        );

        $route = app('router')
            ->getRoutes()
            ->match($request);

        $route->setParameter('thread', $thread);

        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $student);

        // Assert
        $this->assertTrue($request->authorize());
    }

    public function test_authorize_returns_true_for_coach(): void
    {
        // Arrange
        $coach = User::factory()
            ->coach()
            ->create();

        $thread = QaThread::factory()->create();

        CertificationCoachAssignment::factory()->create([
            'certification_id' => $thread->certification_id,
            'user_id' => $coach->id,
        ]);

        $request = StoreRequest::create(
            route('qa-board.replies.store', $thread),
            'POST'
        );

        $route = app('router')
            ->getRoutes()
            ->match($request);

        $route->setParameter('thread', $thread);

        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $coach);

        // Assert
        $this->assertTrue($request->authorize());
    }

    public function test_authorize_returns_false_for_admin(): void
    {
        // Arrange
        $admin = User::factory()
            ->admin()
            ->create();

        $thread = QaThread::factory()->create();

        $request = StoreRequest::create(
            route('qa-board.replies.store', $thread),
            'POST'
        );

        $route = app('router')
            ->getRoutes()
            ->match($request);

        $route->setParameter('thread', $thread);

        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $admin);

        // Assert
        $this->assertFalse($request->authorize());
    }
}
