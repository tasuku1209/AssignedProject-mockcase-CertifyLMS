<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaThread;

use App\Enums\QaThreadStatus;
use App\Http\Requests\QaThread\IndexAsAdminRequest;
use App\Models\Certification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 質問掲示板 管理者用一覧 IndexAsAdminRequest の rules() を検証する Unit テスト。
 *
 * keyword / status / certification_id / page のバリデーションを網羅する。
 */
class IndexAsAdminRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_empty_filters(): void
    {
        $validator = Validator::make(
            [],
            (new IndexAsAdminRequest)->rules()
        );

        $this->assertTrue($validator->passes());
    }

    public function test_passes_with_valid_keyword(): void
    {
        $validator = Validator::make(
            ['keyword' => 'Laravel'],
            (new IndexAsAdminRequest)->rules()
        );

        $this->assertTrue($validator->passes());
    }

    public function test_passes_with_valid_status(): void
    {
        $validator = Validator::make(
            ['status' => QaThreadStatus::Unresolved->value],
            (new IndexAsAdminRequest)->rules()
        );

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_status_is_invalid(): void
    {
        $validator = Validator::make(
            ['status' => 'invalid-status'],
            (new IndexAsAdminRequest)->rules()
        );

        $this->assertArrayHasKey(
            'status',
            $validator->errors()->toArray()
        );
    }

    public function test_fails_when_keyword_exceeds_max(): void
    {
        $validator = Validator::make(
            ['keyword' => str_repeat('a', 101)],
            (new IndexAsAdminRequest)->rules()
        );

        $this->assertArrayHasKey(
            'keyword',
            $validator->errors()->toArray()
        );
    }

    public function test_passes_with_existing_published_certification_id(): void
    {
        $certification = Certification::factory()
            ->published()
            ->create();

        $validator = Validator::make(
            ['certification_id' => $certification->id],
            (new IndexAsAdminRequest)->rules()
        );

        $this->assertTrue($validator->passes());
    }

    public function test_passes_with_existing_unpublished_certification_id(): void
    {
        $certification = Certification::factory()->create();

        $validator = Validator::make(
            ['certification_id' => $certification->id],
            (new IndexAsAdminRequest)->rules()
        );

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_certification_does_not_exist(): void
    {
        $validator = Validator::make(
            ['certification_id' => (string) Str::ulid()],
            (new IndexAsAdminRequest)->rules()
        );

        $this->assertArrayHasKey(
            'certification_id',
            $validator->errors()->toArray()
        );
    }

    public function test_fails_when_page_is_less_than_one(): void
    {
        $validator = Validator::make(
            ['page' => 0],
            (new IndexAsAdminRequest)->rules()
        );

        $this->assertArrayHasKey(
            'page',
            $validator->errors()->toArray()
        );
    }
}
