<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Profile;

use App\Enums\UserRole;
use App\Http\Requests\Profile\UpdateRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_student_payload(): void
    {
        // Arrange
        $user = User::factory()->student()->create();

        $payload = [
            'name' => '受講生太郎',
            'bio' => '自己紹介です。',
        ];

        $request = new UpdateRequest;
        $request->setUserResolver(fn () => $user);

        // Act
        $validator = Validator::make($payload, $request->rules());

        // Assert
        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    public function test_passes_with_valid_coach_payload(): void
    {
        // Arrange
        $user = User::factory()->coach()->create();

        $payload = [
            'name' => 'コーチ太郎',
            'bio' => 'コーチの自己紹介です。',
            'meeting_url' => 'https://meet.google.com/example-room',
        ];

        $request = new UpdateRequest;
        $request->setUserResolver(fn () => $user);

        // Act
        $validator = Validator::make($payload, $request->rules());

        // Assert
        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    #[DataProvider('invalidCommonCases')]
    public function test_fails_for_invalid_common_field(string $field, mixed $value): void
    {
        // Arrange
        $user = User::factory()->student()->create();

        $payload = [
            'name' => '受講生太郎',
            'bio' => '自己紹介です。',
        ];

        $payload[$field] = $value;

        $request = new UpdateRequest;
        $request->setUserResolver(fn () => $user);

        // Act
        $validator = Validator::make($payload, $request->rules());

        // Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey($field, $validator->errors()->toArray());
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function invalidCommonCases(): array
    {
        return [
            'name 未指定でエラー' => ['name', ''],
            'name 51 文字でエラー' => ['name', str_repeat('a', 51)],
            'name 数値でエラー' => ['name', 123],
            'bio 1001 文字でエラー' => ['bio', str_repeat('a', 1001)],
            'bio 配列でエラー' => ['bio', ['invalid']],
        ];
    }

    #[DataProvider('invalidMeetingUrlCases')]
    public function test_coach_fails_for_invalid_meeting_url(mixed $value): void
    {
        // Arrange
        $user = User::factory()->coach()->create();

        $payload = [
            'name' => 'コーチ太郎',
            'bio' => 'コーチの自己紹介です。',
            'meeting_url' => $value,
        ];

        $request = new UpdateRequest;
        $request->setUserResolver(fn () => $user);

        // Act
        $validator = Validator::make($payload, $request->rules());

        // Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey(
            'meeting_url',
            $validator->errors()->toArray()
        );
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidMeetingUrlCases(): array
    {
        return [
            'URL形式でない値でエラー' => ['not-a-url'],
            '500文字を超えるURLでエラー' => [
                'https://example.com/'.str_repeat('a', 500),
            ],
        ];
    }

    public function test_meeting_url_rule_is_applied_only_to_coach(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $coachRequest = new UpdateRequest;
        $coachRequest->setUserResolver(fn () => $coach);

        $studentRequest = new UpdateRequest;
        $studentRequest->setUserResolver(fn () => $student);

        // Act
        $coachRules = $coachRequest->rules();
        $studentRules = $studentRequest->rules();

        // Assert
        $this->assertArrayHasKey('meeting_url', $coachRules);
        $this->assertArrayNotHasKey('meeting_url', $studentRules);
        $this->assertSame(UserRole::Coach, $coach->role);
        $this->assertSame(UserRole::Student, $student->role);
    }
}
