<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaThread;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Http\Requests\QaThread\IndexRequest;
use App\Models\Certification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * 質問掲示板一覧 IndexRequest の rules() を検証する Unit テスト。
 *
 * keyword / status / certification_id / page のバリデーションを網羅する。
 */
class IndexRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_empty_filters(): void
    {
        $validator = Validator::make(
            [],
            (new IndexRequest)->rules()
        );

        $this->assertTrue($validator->passes());
    }

    public function test_passes_with_valid_keyword(): void
    {
        $validator = Validator::make(
            ['keyword' => 'Laravel'],
            (new IndexRequest)->rules()
        );

        $this->assertTrue($validator->passes());
    }

    public function test_passes_with_valid_status(): void
    {
        $validator = Validator::make(
            ['status' => QaThreadStatus::Unresolved->value],
            (new IndexRequest)->rules()
        );

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_status_is_invalid(): void
    {
        $validator = Validator::make(
            ['status' => 'invalid-status'],
            (new IndexRequest)->rules()
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
            (new IndexRequest)->rules()
        );

        $this->assertArrayHasKey(
            'keyword',
            $validator->errors()->toArray()
        );
    }

    public function test_passes_with_published_certification_id(): void
    {
        $certification = Certification::factory()
            ->published()
            ->create();

        $validator = Validator::make(
            ['certification_id' => $certification->id],
            (new IndexRequest)->rules()
        );

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_certification_does_not_exist(): void
    {
        $certification = Certification::factory()
            ->published()
            ->create();

        $validator = Validator::make(
            ['certification_id' => '01invalidulid0000000000000000'],
            (new IndexRequest)->rules()
        );

        $this->assertArrayHasKey(
            'certification_id',
            $validator->errors()->toArray()
        );
    }

    public function test_fails_when_certification_is_not_published(): void
    {
        $certification = Certification::factory()->create([
            'status' => CertificationStatus::Draft->value,
        ]);

        $validator = Validator::make(
            ['certification_id' => $certification->id],
            (new IndexRequest)->rules()
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
            (new IndexRequest)->rules()
        );

        $this->assertArrayHasKey(
            'page',
            $validator->errors()->toArray()
        );
    }
}
