<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_qa_thread_list(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        QaThread::factory()
            ->for($student, 'user')
            ->for($certification)
            ->create([
                'title' => '公開資格の質問',
            ]);

        $response = $this->actingAs($student)
            ->get(route('qa-board.index'));

        $response->assertOk();
        $response->assertViewIs('qa-thread.index');
        $response->assertViewHas('threads');
        $response->assertSee('公開資格の質問');
    }

    public function test_student_sees_only_threads_of_published_certifications(): void
    {
        $student = User::factory()->student()->create();

        $publishedCertification = Certification::factory()
            ->published()
            ->create();

        $draftCertification = Certification::factory()
            ->create();

        QaThread::factory()
            ->for($publishedCertification)
            ->create([
                'title' => '公開資格の質問',
            ]);

        QaThread::factory()
            ->for($draftCertification)
            ->create([
                'title' => '非公開資格の質問',
            ]);

        $response = $this->actingAs($student)
            ->get(route('qa-board.index'));

        $response->assertOk();
        $response->assertSee('公開資格の質問');
        $response->assertDontSee('非公開資格の質問');
    }

    public function test_coach_sees_only_threads_of_assigned_certifications(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();

        $assignedCertification = Certification::factory()
            ->published()
            ->create();

        $unassignedCertification = Certification::factory()
            ->published()
            ->create();

        $assignedCertification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        QaThread::factory()
            ->for($assignedCertification)
            ->create([
                'title' => '担当資格の質問',
            ]);

        QaThread::factory()
            ->for($unassignedCertification)
            ->create([
                'title' => '未担当資格の質問',
            ]);

        $response = $this->actingAs($coach)
            ->get(route('qa-board.index'));

        $response->assertOk();
        $response->assertSee('担当資格の質問');
        $response->assertDontSee('未担当資格の質問');
    }

    public function test_keyword_filter_matches_title_or_body(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        QaThread::factory()
            ->for($certification)
            ->create([
                'title' => 'Laravelについて',
                'body' => 'Laravelの質問です。',
            ]);

        QaThread::factory()
            ->for($certification)
            ->create([
                'title' => 'PHPについて',
                'body' => 'PHPの質問です。',
            ]);

        $response = $this->actingAs($student)
            ->get(route('qa-board.index', [
                'keyword' => 'Laravel',
            ]));

        $response->assertOk();
        $response->assertSee('Laravelについて');
        $response->assertDontSee('PHPについて');
    }

    public function test_status_filter_returns_only_matching_threads(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        QaThread::factory()
            ->for($certification)
            ->unresolved()
            ->create([
                'title' => '未解決の質問',
            ]);

        QaThread::factory()
            ->for($certification)
            ->resolved()
            ->create([
                'title' => '解決済みの質問',
            ]);

        $response = $this->actingAs($student)
            ->get(route('qa-board.index', [
                'status' => 'resolved',
            ]));

        $response->assertOk();
        $response->assertSee('解決済みの質問');
        $response->assertDontSee('未解決の質問');
    }

    public function test_certification_filter_returns_only_matching_threads(): void
    {
        $student = User::factory()->student()->create();

        $targetCertification = Certification::factory()
            ->published()
            ->create();

        $otherCertification = Certification::factory()
            ->published()
            ->create();

        QaThread::factory()
            ->for($targetCertification)
            ->create([
                'title' => '対象資格の質問',
            ]);

        QaThread::factory()
            ->for($otherCertification)
            ->create([
                'title' => '別資格の質問',
            ]);

        $response = $this->actingAs($student)
            ->get(route('qa-board.index', [
                'certification_id' => $targetCertification->id,
            ]));

        $response->assertOk();
        $response->assertSee('対象資格の質問');
        $response->assertDontSee('別資格の質問');
    }

    public function test_combined_filters_return_only_matching_threads(): void
    {
        $student = User::factory()->student()->create();

        $targetCertification = Certification::factory()
            ->published()
            ->create();

        $otherCertification = Certification::factory()
            ->published()
            ->create();

        QaThread::factory()
            ->for($targetCertification)
            ->unresolved()
            ->create([
                'title' => 'Laravel 未解決',
                'body' => 'Laravelについて',
            ]);

        QaThread::factory()
            ->for($targetCertification)
            ->resolved()
            ->create([
                'title' => 'Laravel 解決済み',
                'body' => 'Laravelについて',
            ]);

        QaThread::factory()
            ->for($otherCertification)
            ->unresolved()
            ->create([
                'title' => 'Laravel 別資格',
                'body' => 'Laravelについて',
            ]);

        $response = $this->actingAs($student)
            ->get(route('qa-board.index', [
                'keyword' => 'Laravel',
                'status' => 'unresolved',
                'certification_id' => $targetCertification->id,
            ]));

        $response->assertOk();
        $response->assertSee('Laravel 未解決');
        $response->assertDontSee('Laravel 解決済み');
        $response->assertDontSee('Laravel 別資格');
    }

    public function test_paginates_20_threads_per_page(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        QaThread::factory()
            ->for($certification)
            ->count(22)
            ->create();

        $response = $this->actingAs($student)
            ->get(route('qa-board.index'));

        $response->assertOk();

        $threads = $response->viewData('threads');

        $this->assertSame(20, $threads->perPage());
        $this->assertSame(22, $threads->total());
    }
}
