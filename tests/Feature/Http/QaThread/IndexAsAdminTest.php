<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexAsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_all_qa_threads(): void
    {
        $admin = User::factory()->admin()->create();

        QaThread::factory()->count(3)->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.qa-board.index'));

        $response->assertOk();
        $response->assertViewIs('qa-thread.index');
        $response->assertViewHas('threads');
        $response->assertViewHas('certifications');
    }

    public function test_admin_can_view_threads_of_unpublished_certification(): void
    {
        $admin = User::factory()->admin()->create();

        $certification = Certification::factory()->draft()->create();

        QaThread::factory()
            ->forCertification($certification)
            ->create([
                'title' => '非公開資格の質問',
            ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.qa-board.index'));

        $response->assertOk();
        $response->assertSee('非公開資格の質問');
    }

    public function test_non_admin_cannot_access_admin_qa_thread_index(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('admin.qa-board.index'))
            ->assertForbidden();
    }

    public function test_keyword_filter_matches_title_or_body(): void
    {
        $admin = User::factory()->admin()->create();

        QaThread::factory()->create([
            'title' => 'Laravelについての質問',
            'body' => '本文です。',
        ]);

        QaThread::factory()->create([
            'title' => '別の質問',
            'body' => 'Laravelのバリデーションについて。',
        ]);

        QaThread::factory()->create([
            'title' => 'PHPについての質問',
            'body' => '別の本文です。',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.qa-board.index', [
                'keyword' => 'Laravel',
            ]));

        $response->assertOk();
        $response->assertSee('Laravelについての質問');
        $response->assertSee('別の質問');
        $response->assertDontSee('PHPについての質問');
    }

    public function test_status_filter_returns_only_matching_threads(): void
    {
        $admin = User::factory()->admin()->create();

        QaThread::factory()->unresolved()->create([
            'title' => '未解決の質問',
        ]);

        QaThread::factory()->resolved()->create([
            'title' => '解決済みの質問',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.qa-board.index', [
                'status' => 'resolved',
            ]));

        $response->assertOk();
        $response->assertSee('解決済みの質問');
        $response->assertDontSee('未解決の質問');
    }

    public function test_certification_filter(): void
    {
        $admin = User::factory()->admin()->create();

        $certification = Certification::factory()->published()->create();

        QaThread::factory()
            ->forCertification($certification)
            ->create([
                'title' => '対象資格の質問',
            ]);

        QaThread::factory()->create([
            'title' => '別資格の質問',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.qa-board.index', [
                'certification_id' => $certification->id,
            ]));

        $response->assertOk();
        $response->assertSee('対象資格の質問');
        $response->assertDontSee('別資格の質問');
    }

    public function test_paginates_20_per_page(): void
    {
        $admin = User::factory()->admin()->create();

        QaThread::factory()->count(22)->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.qa-board.index'));

        $response->assertOk();

        $threads = $response->viewData('threads');

        $this->assertSame(20, $threads->perPage());
        $this->assertSame(22, $threads->total());
    }
}
