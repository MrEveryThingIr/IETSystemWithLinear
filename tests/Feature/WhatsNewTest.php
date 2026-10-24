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

        $this->assertSame(trim(file_get_contents(base_path('VERSION'))), Changelog::currentVersion());
    }

    public function test_source_version_templates_and_release_notes_stay_in_sync(): void
    {
        $version = trim(file_get_contents(base_path('VERSION')));
        $this->assertSame('2.0.0', $version);
        $this->assertSame($version, config('app.version'));
        $this->assertSame($version, array_key_first(Changelog::RELEASES));
        foreach (['.env.example', 'deploy/production.env.example'] as $template) {
            $this->assertStringContainsString('APP_VERSION='.$version, file_get_contents(base_path($template)));
        }
        $actor = Actor::factory()->create();
        $this->actingAs($actor->user)->get(route('whats-new'))->assertOk()
            ->assertSee('data-release="'.$version.'"', false)
            ->assertSee(config('app.name').' '.$version);
    }

    public function test_a_package_manifest_takes_precedence_and_malformed_versions_fall_back(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'iet-version-');
        try {
            config(['app.version' => '1.8.0']);
            file_put_contents($path, json_encode(['application_version' => '1.12.0']));
            $this->assertSame('1.12.0', Changelog::currentVersion($path));
            file_put_contents($path, json_encode(['application_version' => '1.8.0garbage']));
            $this->assertSame(trim(file_get_contents(base_path('VERSION'))), Changelog::currentVersion($path));
        } finally {
            unlink($path);
        }
    }
}
