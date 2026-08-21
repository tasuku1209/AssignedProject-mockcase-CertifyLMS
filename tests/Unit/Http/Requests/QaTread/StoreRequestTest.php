<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaThread;

use App\Http\Requests\QaThread\StoreRequest;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 質問掲示板スレッド作成 StoreRequest のバリデーション・認可を検証する。
 *
 * certification_id / title / body のバリデーションと、
 * QaThreadPolicy::create による受講生のみの認可を検証する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_full_valid_payload(): void
    {
        // Arrange
        $certification = Certification::factory()
            ->published()
            ->create();

        $payload = [
            'certification_id' => $certification->id,
            'title' => 'Laravelについて質問があります',
            'body' => 'Laravelの認証処理について教えてください。',
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
        $certification = Certification::factory()
            ->published()
            ->create();

        $payload = array_merge(
            [
                'certification_id' => $certification->id,
                'title' => 'Laravelについて質問があります',
                'body' => 'Laravelの認証処理について教えてください。',
            ],
            [$field => $value]
        );

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
            'certification_id 未指定で エラー' => ['certification_id', ''],
            'title 未指定で エラー' => ['title', ''],
            'title 201 文字で エラー' => ['title', str_repeat('a', 201)],
            'body 未指定で エラー' => ['body', ''],
            'body 5001 文字で エラー' => ['body', str_repeat('b', 5001)],
        ];
    }

    public function test_authorize_returns_true_for_student(): void
    {
        $student = User::factory()
            ->student()
            ->create();

        $request = StoreRequest::create(
            route('qa-board.store'),
            'POST'
        );

        $request->setUserResolver(fn () => $student);

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_returns_false_for_coach(): void
    {
        $coach = User::factory()
            ->coach()
            ->create();

        $request = StoreRequest::create(
            route('qa-board.store'),
            'POST'
        );

        $request->setUserResolver(fn () => $coach);

        $this->assertFalse($request->authorize());
    }

    public function test_authorize_returns_false_for_admin(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $request = StoreRequest::create(
            route('qa-board.store'),
            'POST'
        );

        $request->setUserResolver(fn () => $admin);

        $this->assertFalse($request->authorize());
    }
}
