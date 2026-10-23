<?php

namespace Tests\Feature;

use App\CalendarSystem;
use App\Models\Actor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AmbientClockExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_home_has_one_analog_face_and_the_shared_bar_without_losing_access(): void
    {
        $this->withoutVite()->get('/')->assertOk()
            ->assertSee('data-home-clock', false)
            ->assertSee('data-analog-clock', false)
            ->assertSee('data-ambient-bar', false)
            ->assertSee('ambient-face-brand', false)
            ->assertSee(route('login'), false)
            ->assertSee('id="home-content"', false);
    }

    public function test_authenticated_home_uses_existing_temporal_preferences(): void
    {
        $actor = Actor::factory()->create();
        $actor->user->forceFill([
            'locale' => 'fa', 'calendar' => CalendarSystem::Persian,
            'timezone' => 'Asia/Tehran', 'date_display_format' => 'numeric',
            'time_display_format' => '12h', 'gregorian_equivalent_mode' => 'hover',
        ])->save();

        $this->withoutVite()->actingAs($actor->user)->get('/')->assertOk()
            ->assertSee('data-calendar="persian"', false)
            ->assertSee('data-timezone="Asia/Tehran"', false)
            ->assertSee('data-date-format="numeric"', false)
            ->assertSee('data-time-format="12h"', false)
            ->assertSee('data-equivalent-mode="hover"', false)
            ->assertSee('data-temporal-popover-trigger', false)
            ->assertSee(route('dashboard'), false);
    }

    public function test_inline_and_hidden_equivalents_remain_user_choices(): void
    {
        $actor = Actor::factory()->create();
        foreach (['inline', 'hidden'] as $mode) {
            $actor->user->forceFill(['calendar' => CalendarSystem::Persian, 'gregorian_equivalent_mode' => $mode])->save();
            $this->withoutVite()->actingAs($actor->user)->get('/')->assertOk()
                ->assertSee('data-equivalent-mode="'.$mode.'"', false)
                ->assertSee('data-show-equivalent="'.($mode === 'hidden' ? 'false' : 'true').'"', false);
        }
    }

    public function test_guest_authentication_and_public_pages_have_the_permanent_bar(): void
    {
        foreach (['/', '/login', '/forgot-password', '/public'] as $path) {
            $response = $this->withoutVite()->get($path)->assertOk();
            $this->assertSame(1, substr_count($response->getContent(), 'data-ambient-bar'));
        }
    }

    public function test_planning_baseline_still_has_the_shared_clock(): void
    {
        config(['release.profile' => 'planning_baseline']);
        $actor = Actor::factory()->create();
        $this->withoutVite()->actingAs($actor->user)->get('/dashboard')->assertOk()->assertSee('data-ambient-bar', false);
    }

    public function test_every_standalone_application_document_includes_the_clock(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            $source = $file->getContents();
            if (! str_contains(strtolower($source), '<body')) {
                continue;
            }
            $this->assertTrue(str_contains($source, '<x-app.ambient-bar') || str_contains($source, '<x-app.shell'), $file->getRelativePathname());
        }
    }
}
