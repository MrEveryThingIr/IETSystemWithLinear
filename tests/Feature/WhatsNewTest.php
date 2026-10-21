<?php

namespace Tests\Feature;

use App\Models\Actor;
use App\Support\Changelog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsNewTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_version_is_shown_next_to_the_site_name_and_opens_whats_new(): void
    {
        config(['app.version' => 'v1.5.0', 'app.name' => 'Everything']);
        $actor = Actor::factory()->create();

        $this->assertSame('1.5.0', Changelog::currentVersion());
        $this->actingAs($actor->user)->get(route('whats-new'))
            ->assertOk()
            ->assertSee('data-versioned-brand', false)
            ->assertSee('data-app-version', false)
            ->assertSee('Everything 1.5.0')
            ->assertSee(__('changelog.current'))
            ->assertSee(__('changelog.versions.1_5_0.title'))
            ->assertSee(__('changelog.versions.1_4_0.title'));
    }

    public function test_every_release_has_notes_in_every_language(): void
    {
        foreach (['en', 'fa', 'ar', 'zh_CN'] as $locale) {
            app()->setLocale($locale);
            foreach (Changelog::entries() as $entry) {
                $this->assertNotSame('', $entry['title'], $locale.' '.$entry['version']);
                $this->assertNotEmpty($entry['items'], $locale.' '.$entry['version']);
            }
        }
    }

    public function test_a_development_checkout_shows_the_newest_release(): void
    {
        config(['app.version' => 'local']);

        $this->assertSame(array_key_first(Changelog::RELEASES), Changelog::currentVersion());
    }
}
