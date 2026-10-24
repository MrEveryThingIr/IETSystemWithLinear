<?php

namespace Tests\Feature;

use App\Livewire\Home\RecentActivity;
use App\Livewire\Home\Today;
use App\Models\Actor;
use App\Models\User;
use App\PlanOccurrenceEventType;
use App\Support\Localization;
use App\Support\RequestMemo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

/**
 * Guards for layout and speed problems that were found on phones and that are
 * easy to reintroduce without noticing on a desktop screen.
 */
class ResponsiveGuardsTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<SplFileInfo> */
    private function views(): array
    {
        return array_values(array_filter(
            File::allFiles(resource_path('views')),
            fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.blade.php'),
        ));
    }

    public function test_js_directive_is_never_used_inside_component_tags(): void
    {
        // @js() is not compiled inside <flux:...> / <x-...> attributes and
        // breaks the button's click handler; use Js::from() there instead.
        $offenders = [];
        foreach ($this->views() as $file) {
            if (preg_match_all('/<(flux:[a-z.-]+|x-[a-z.-]+)\b[^>]*?@js\(/s', $file->getContents(), $matches) > 0) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders);
    }

    public function test_grids_and_hidden_fields_never_force_a_minimum_page_width(): void
    {
        $offenders = [];
        foreach ($this->views() as $file) {
            $contents = $file->getContents();
            // An auto-filling card grid with a fixed minimum is wider than a 320px phone.
            if (preg_match('/repeat\(\s*auto-(fill|fit)\s*,\s*minmax\(\s*\d/', $contents) === 1
                // Large negative offsets become scrollable space in right-to-left pages.
                || preg_match('/(left|right)\s*:\s*-\d{3,}px/', $contents) === 1) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders);
    }

    public function test_stylesheet_keeps_the_layout_guards_and_self_hosted_fonts(): void
    {
        $css = File::get(resource_path('css/app.css'));

        $this->assertStringNotContainsString('fonts.googleapis.com', $css);
        $this->assertStringContainsString("@import '@fontsource-variable/vazirmatn'", $css);
        $this->assertStringContainsString('grid-auto-columns: minmax(0, 1fr)', $css);
        $this->assertMatchesRegularExpression('/html\s*\{\s*overflow-x:\s*hidden;/', $css);
        $this->assertStringContainsString('ui-table-scroll-area', $css);
    }

    public function test_avatars_cannot_be_squeezed_by_their_container(): void
    {
        $this->assertStringContainsString('max-w-none shrink-0', File::get(resource_path('views/components/app/actor-avatar.blade.php')));
    }

    public function test_timeline_events_and_today_guidance_are_translated_in_every_language(): void
    {
        $keys = array_map(
            fn (PlanOccurrenceEventType $type): string => 'planner.events.occurrence_'.$type->value,
            PlanOccurrenceEventType::cases(),
        );
        foreach (['relationship', 'proposal', 'contract', 'fulfillment', 'submission', 'money', 'planner'] as $kind) {
            $keys[] = 'home.guidance.consequence.'.$kind;
        }

        $missing = [];
        foreach (Localization::codes() as $locale) {
            foreach ($keys as $key) {
                if (__($key, [], $locale) === $key) {
                    $missing[] = $locale.': '.$key;
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function test_remembered_answers_are_forgotten_after_any_database_write(): void
    {
        $user = User::factory()->create();
        $calls = 0;
        RequestMemo::remember('probe', function () use (&$calls): int {
            return ++$calls;
        });
        RequestMemo::remember('probe', function () use (&$calls): int {
            return ++$calls;
        });
        $this->assertSame(1, $calls);

        $user->forceFill(['locale' => 'fa'])->save();
        RequestMemo::remember('probe', function () use (&$calls): int {
            return ++$calls;
        });
        $this->assertSame(2, $calls);
    }

    public function test_recent_activity_loads_on_its_own_after_today(): void
    {
        $me = Actor::factory()->create();

        Livewire::actingAs($me->user)->test(Today::class)
            ->assertDontSeeHtml('data-today-recent-activity');

        Livewire::actingAs($me->user)->withoutLazyLoading()->test(RecentActivity::class)
            ->assertSeeHtml('data-today-recent-activity')
            ->assertSee(__('home.recent_activity'));
    }
}
