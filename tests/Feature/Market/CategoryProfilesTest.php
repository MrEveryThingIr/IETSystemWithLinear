<?php

namespace Tests\Feature\Market;

use App\Livewire\Intents\Composer;
use App\Livewire\Intents\Directory;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Support\Market\IntentDetailCatalog;
use App\Support\Market\IntentPresentation;
use App\Support\Market\IntentProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class CategoryProfilesTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    public function test_every_profile_key_exists_and_is_labelled_in_all_four_languages(): void
    {
        $catalog = IntentDetailCatalog::all();
        foreach (IntentProfiles::all() as $topic => $profile) {
            foreach ($profile['fields'] as [$key]) {
                $this->assertArrayHasKey($key, $catalog, "$topic uses unknown key $key");
            }
        }
        foreach (['en', 'fa', 'ar', 'zh_CN'] as $locale) {
            $lang = require lang_path($locale.'/composer.php');
            foreach (array_keys($catalog) as $key) {
                $this->assertNotEmpty($lang['details'][$key] ?? null, "$locale missing label for $key");
            }
            foreach ($catalog as $key => $spec) {
                foreach ($spec['options'] ?? [] as $option) {
                    $this->assertNotEmpty($lang['options'][$option] ?? null, "$locale missing option $option of $key");
                }
            }
            foreach ([...IntentProfiles::TOPICS, 'other'] as $topic) {
                $this->assertNotEmpty($lang['topics'][$topic] ?? null, "$locale missing topic $topic");
            }
        }
    }

    public function test_profiles_ask_only_what_fits_and_conditions_follow_answers(): void
    {
        $keys = fn (string $topic, string $intention, string $arrangement, array $answers = []): array => IntentProfiles::fields($topic, $intention, $arrangement, $answers);

        $sale = $keys('home', 'sell', 'ownership_transfer');
        $this->assertSame(['property_type', 'floor_area', 'rooms'], $sale['essential']);
        $this->assertNotContains('deposit', [...$sale['essential'], ...$sale['suggested']]);
        $this->assertContains('deposit', $keys('home', 'rent', 'temporary_use')['essential']);
        $this->assertContains('deposit', $keys('home', 'need', 'temporary_use')['suggested']);

        $this->assertNotContains('mileage_km', $keys('vehicle', 'sell', 'ownership_transfer', ['condition' => 'new'])['essential']);
        $this->assertContains('mileage_km', $keys('vehicle', 'sell', 'ownership_transfer', ['condition' => 'used'])['essential']);
        $this->assertNotContains('pay', $keys('job', 'person', 'collaboration', ['pay_period' => 'agreed'])['essential']);
        $this->assertContains('pay', $keys('job', 'person', 'collaboration', ['pay_period' => 'month'])['essential']);

        $activity = $keys('activity', 'activity', 'collaboration');
        foreach (['brand', 'condition', 'floor_area', 'mileage_km'] as $irrelevant) {
            $this->assertNotContains($irrelevant, [...$activity['essential'], ...$activity['suggested']]);
        }
        $this->assertSame(['activity'], IntentProfiles::topics('activity'));
        $this->assertSame('collaboration', IntentProfiles::arrangement('job', 'ownership_transfer'));
    }

    public function test_switching_topics_and_arrangements_keeps_answers_and_removes_empty_questions(): void
    {
        $component = $this->composer()->call('choose', 'sell')->call('chooseTopic', 'home');
        $this->assertSame(['property_type', 'floor_area', 'rooms'], $this->keys($component));
        $this->assertSame('property', $component->get('core.subject_kind'));
        $this->assertSame('ownership_transfer', $component->get('core.arrangement_kind'));

        $component->call('chooseArrangement', 'temporary_use')->assertSet('priceMode', 'per_unit')->assertSet('core.cash_basis', 'month');
        $this->assertContains('deposit', $this->keys($component));
        $component->call('chooseArrangement', 'ownership_transfer')->assertSet('priceMode', 'fixed');
        $this->assertNotContains('deposit', $this->keys($component), 'an empty rent-only question disappears on sale');

        $index = $this->index($component, 'floor_area');
        $component->set("details.$index.value", '85')->call('chooseTopic', 'vehicle');
        $this->assertContains('floor_area', $this->keys($component), 'an answered detail is never silently discarded');
        $this->assertContains('brand', $this->keys($component));
        $this->assertNotContains('property_type', $this->keys($component), 'an empty question of the old topic is gone');
    }

    public function test_conditional_questions_appear_and_disappear_with_answers(): void
    {
        $component = $this->composer()->call('choose', 'sell')->call('chooseTopic', 'vehicle');
        $this->assertNotContains('mileage_km', $this->keys($component));
        $component->set('details.'.$this->index($component, 'condition').'.value', 'used');
        $this->assertContains('mileage_km', $this->keys($component));
        $component->set('details.'.$this->index($component, 'condition').'.value', 'new');
        $this->assertNotContains('mileage_km', $this->keys($component));
    }

    public function test_unanswered_questions_are_not_saved_and_the_topic_survives_a_reload(): void
    {
        $user = Actor::factory()->create()->user;
        $component = Livewire::actingAs($user)->test(Composer::class)->call('choose', 'sell')->call('chooseTopic', 'home')
            ->set('core.description', 'Bright flat near the metro');
        $component->set('details.'.$this->index($component, 'floor_area').'.value', '۸۵');
        $component->call('publish')->assertHasNoErrors();

        $post = ActorProfileIntent::query()->sole();
        $snapshot = $post->publishedVersion->snapshot;
        $this->assertSame(['floor_area'], array_column($snapshot['details'], 'canonical_key'));
        $this->assertEquals(85, $snapshot['details'][0]['payload']['value']);
        $this->assertDatabaseHas('intent_search_values', ['intent_version_id' => $post->published_version_id, 'canonical_key' => 'floor_area']);
        $this->assertSame('home', $post->fresh()->metadata['composer_topic']);

        $reloaded = Livewire::actingAs($user)->test(Composer::class, ['intent' => $post->fresh()]);
        $reloaded->assertSet('topic', 'home');
        $this->assertSame(['floor_area', 'property_type', 'rooms'], collect($this->keys($reloaded))->sort()->values()->all());
    }

    public function test_toggles_and_choices_filter_the_directory_and_present_in_the_viewers_language(): void
    {
        $owner = Actor::factory()->create()->user;
        $this->publishSurfaces($owner, ['market']);
        $component = Livewire::actingAs($owner)->test(Composer::class)->call('choose', 'sell')->call('chooseTopic', 'home')
            ->set('core.title', 'Flat with parking')->set('core.visibility', 'authenticated');
        $component->set('details.'.$this->index($component, 'property_type').'.value', 'apartment');
        $component->call('addDetail', 'parking');
        $component->set('details.'.$this->index($component, 'parking').'.value', true);
        $component->call('publish')->assertHasNoErrors();
        $post = ActorProfileIntent::query()->sole();

        $viewer = Actor::factory()->create()->user;
        $this->publishSurfaces($viewer, ['market']);
        Livewire::actingAs($viewer)->test(Directory::class)->set('detail', 'parking')->set('detailText', '1')->assertSee('Flat with parking');
        Livewire::actingAs($viewer)->test(Directory::class)->set('detail', 'elevator')->set('detailText', '1')->assertDontSee('Flat with parking');
        Livewire::actingAs($viewer)->test(Directory::class)->set('detail', 'property_type')->set('detailText', 'apartment')->assertSee('Flat with parking');
        Livewire::actingAs($viewer)->test(Directory::class)->set('detail', 'property_type')->set('detailText', 'land')->assertDontSee('Flat with parking');

        app()->setLocale('fa');
        $shown = app(IntentPresentation::class)->published($post->fresh(), $viewer);
        $this->assertContains('آپارتمان', array_column($shown['details'], 'value'));
    }

    private function composer(): Testable
    {
        return Livewire::actingAs(Actor::factory()->create()->user)->test(Composer::class);
    }

    /** @return list<string> */
    private function keys(Testable $component): array
    {
        return array_values(array_filter(array_column($component->get('details'), 'canonical_key')));
    }

    private function index(Testable $component, string $key): int
    {
        foreach ($component->get('details') as $index => $row) {
            if (($row['canonical_key'] ?? null) === $key) {
                return $index;
            }
        }
        $this->fail("No row for $key");
    }
}
