<?php

namespace Tests\Feature;

use App\Actions\Development\CaptureDevelopmentOrigin;
use App\Livewire\Platform\DevelopmentOrigins;
use App\Models\Actor;
use App\Models\DevelopmentOrigin;
use App\Models\PlatformAccessGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DevelopmentOriginTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_auditor_can_capture_immutable_chat_origin_with_repository_and_version_provenance(): void
    {
        $actor = Actor::factory()->create();
        PlatformAccessGrant::factory()->create(['user_id' => $actor->user_id]);

        $origin = app(CaptureDevelopmentOrigin::class)->execute(
            $actor->user,
            'chatgpt',
            'https://chatgpt.com/share/example',
            'AI-assisted authoring direction',
            'The discussion established plan-first AI assistance and development provenance.',
            'phase-07-cross-cutting',
            '0.7-dev',
            'feat/context-ai-assistance-provenance',
            str_repeat('a', 40),
            str_repeat('b', 40),
            ['docs/PROJECT_COMPASS.md', 'docs/PRODUCTION_ROADMAP.md'],
            '2026-09-23 18:05:00',
        );

        $this->assertSame('chatgpt', $origin->source_type);
        $this->assertSame('phase-07-cross-cutting', $origin->phase_key);
        $this->assertSame(['docs/PROJECT_COMPASS.md', 'docs/PRODUCTION_ROADMAP.md'], $origin->repository_paths);

        $this->expectException(\LogicException::class);
        $origin->update(['title' => 'Rewritten history']);
    }

    public function test_normal_user_cannot_view_or_capture_development_origins(): void
    {
        $actor = Actor::factory()->create();

        try {
            app(CaptureDevelopmentOrigin::class)->execute(
                $actor->user,
                'manual_note',
                null,
                'Private',
                'Should not be written.',
                null,
                null,
                null,
                null,
                null,
                [],
            );
            $this->fail('A normal user should not capture platform development provenance.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        Livewire::actingAs($actor->user)
            ->test(DevelopmentOrigins::class)
            ->assertForbidden();

        $this->assertDatabaseCount('development_origins', 0);
    }

    public function test_development_origin_page_lists_captured_provenance_for_platform_auditor(): void
    {
        $actor = Actor::factory()->create();
        PlatformAccessGrant::factory()->create(['user_id' => $actor->user_id]);

        DevelopmentOrigin::factory()->create([
            'created_by_actor_id' => $actor->id,
            'title' => 'Chat origin for Phase 7',
            'phase_key' => 'phase-07',
        ]);

        Livewire::actingAs($actor->user)
            ->test(DevelopmentOrigins::class)
            ->assertSee('Chat origin for Phase 7')
            ->assertSee('phase-07');
    }
}
