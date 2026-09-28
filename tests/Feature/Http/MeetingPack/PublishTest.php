<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_succeeds_when_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()->draft()->create();

        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.publish', $meetingPack))
            ->assertRedirect();

        $meetingPack->refresh();

        $this->assertSame('published', $meetingPack->status->value);
    }

    public function test_publish_fails_when_already_published(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()->published()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.meeting-packs.publish', $meetingPack))
            ->assertStatus(409);

        $this->assertSame(
            'published',
            $meetingPack->refresh()->status->value
        );
    }

    public function test_publish_fails_when_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()->archived()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.meeting-packs.publish', $meetingPack))
            ->assertStatus(409);

        $this->assertSame(
            'archived',
            $meetingPack->refresh()->status->value
        );
    }

    public function test_archive_succeeds_when_published(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()->published()->create();

        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.archive', $meetingPack))
            ->assertRedirect();

        $meetingPack->refresh();

        $this->assertSame('archived', $meetingPack->status->value);
    }

    public function test_archive_fails_when_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()->draft()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.meeting-packs.archive', $meetingPack))
            ->assertStatus(409);

        $this->assertSame(
            'draft',
            $meetingPack->refresh()->status->value
        );
    }

    public function test_archive_fails_when_already_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()->archived()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.meeting-packs.archive', $meetingPack))
            ->assertStatus(409);

        $this->assertSame(
            'archived',
            $meetingPack->refresh()->status->value
        );
    }

    public function test_unarchive_succeeds_when_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()->archived()->create();

        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.unarchive', $meetingPack))
            ->assertRedirect();

        $meetingPack->refresh();

        $this->assertSame('draft', $meetingPack->status->value);
    }

    public function test_unarchive_fails_when_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()->draft()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.meeting-packs.unarchive', $meetingPack))
            ->assertStatus(409);

        $this->assertSame(
            'draft',
            $meetingPack->refresh()->status->value
        );
    }

    public function test_unarchive_fails_when_published(): void
    {
        $admin = User::factory()->admin()->create();
        $meetingPack = MeetingPack::factory()->published()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.meeting-packs.unarchive', $meetingPack))
            ->assertStatus(409);

        $this->assertSame(
            'published',
            $meetingPack->refresh()->status->value
        );
    }
}
