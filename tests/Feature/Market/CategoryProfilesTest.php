<?php

namespace Tests\Feature\Market;

use App\Livewire\Intents\Composer;
use App\Livewire\Intents\Directory;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\IntentDetailTemplate;
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

    public function test_every_library_key_has_label_aliases_and_options_in_all_four_languages(): void
    {
        $catalog = IntentDetailCatalog::codeDefinitions();
        foreach (IntentProfiles::all() as $topic => $profile) {
            foreach ($profile['fields'] as [$key]) {
                $this->assertArrayHasKey($key, $catalog, "$topic uses unknown key $key");
            }
        }
        foreach (['en', 'fa', 'ar', 'zh_CN'] as $locale) {
            $lang = require lang_path($locale.'/composer.php');
            foreach ($catalog as $key => $spec) {
                $this->assertNotEmpty($lang['details'][$key] ?? null, "$locale missing label for $key");
                $this->assertNotEmpty($lang['aliases'][$key] ?? null, "$locale missing aliases for $key");
                $this->assertNotEmpty($lang['domains'][$spec['domain']] ?? null, "$locale missing domain {$spec['domain']}");
                foreach ($spec['options'] ?? [] as $option) {
                    $this->assertNotEmpty($lang['options'][$option] ?? null, "$locale missing option $option of $key");
                }
            }
            foreach ([...IntentProfiles::TOPICS, 'other'] as $topic) {
                $this->assertNotEmpty($lang['topics'][$topic] ?? null, "$locale missing topic $topic");
            }
            foreach (['price', 'place', 'when', 'route', 'quantity', 'category', 'media', 'importance'] as $block) {
                $this->assertNotEmpty($lang['terms'][$block]['label'] ?? null, "$locale missing term $block");
            }
        }
        $this->assertGreaterThanOrEqual(90, count($catalog));
    }

    public function test_profiles_recommend_what_fits_and_conditions_follow_answers(): void
    {
        $keys = fn (string $topic, string $intention, string $arrangement, array $answers = []): array => IntentProfiles::fields($topic, $intention, $arrangement, $answers);

        $sale = $keys('home', 'sell', 'ownership_transfer');
        $this->assertSame(['property_type', 'floor_area', 'rooms'], $sale['essential']);
        $this->assertNotContains('deposit', [...$sale['essential'], ...$sale['suggested']]);
        $this->assertContains('deposit', $keys('home', 'rent', 'temporary_use')['essential']);
        $this->assertContains('mileage_km', $keys('vehicle', 'sell', 'ownership_transfer', ['condition' => 'used'])['essential']);
        $this->assertNotContains('mileage_km', $keys('vehicle', 'sell', 'ownership_transfer', ['condition' => 'new'])['essential']);
        $this->assertNotContains('pay', $keys('job', 'person', 'collaboration', ['pay_period' => 'agreed'])['essential']);

        $activity = $keys('activity', 'activity', 'collaboration');
        foreach (['brand', 'condition', 'floor_area', 'mileage_km'] as $irrelevant) {
            $this->assertNotContains($irrelevant, [...$activity['essential'], ...$activity['suggested']]);
        }
        // Terms follow meaning: a free collaboration never recommends money; an online lesson never recommends a place.
        $this->assertNotContains('price', IntentProfiles::terms('activity', []));
        $this->assertContains('route', IntentProfiles::terms('transport', []));
        $this->assertContains('place', IntentProfiles::terms('lesson', []));
        $this->assertNotContains('place', IntentProfiles::terms('lesson', ['delivery_mode' => 'online']));
        $this->assertNotContains('route', IntentProfiles::terms('vehicle', []));
        $this->assertNotContains('when', IntentProfiles::terms('vehicle', []));
    }

    public function test_the_initial_form_is_small_and_nothing_is_inserted_automatically(): void
    {
        $component = $this->composer()->call('choose', 'offer')->call('chooseTopic', 'vehicle');
        $this->assertSame([], $component->get('details'));
        $this->assertSame([], $component->get('terms'));
        $component->assertDontSeeHtml('data-composer-term')->assertDontSeeHtml('data-composer-picker')
            ->assertSeeHtml('data-composer-suggestions')->assertSeeHtml('data-add-field');
        $html = $component->html();
        foreach (['Fixed price', 'Starts', 'Origin', 'Time window', 'Importance'] as $unrelated) {
            $this->assertStringNotContainsString($unrelated, $html, "$unrelated must not be visible before it is chosen");
        }
    }

    public function test_selected_suggested_and_inapplicable_are_separate_and_input_is_never_deleted(): void
    {
        $component = $this->composer()->call('choose', 'sell')->call('chooseTopic', 'home')->call('addField', 'floor_area')->call('addField', 'floor_area');
        $this->assertSame(['floor_area'], $this->keys($component), 'a field is inserted once');
        $component->set('details.0.value', '85')->call('chooseTopic', 'vehicle');
        $this->assertSame(['floor_area'], $this->keys($component), 'switching topic keeps what was selected');

        $component->call('chooseTopic', 'home')->call('addField', 'deposit')->set('details.1.value', '500');
        $component->assertSee(__('composer.does_not_apply'));
        $component->set('core.description', 'Flat')->call('publish')->assertHasNoErrors();
        $published = ActorProfileIntent::query()->sole()->publishedVersion->snapshot['details'];
        $this->assertSame(['floor_area'], array_column($published, 'canonical_key'), 'a value that does not apply is excluded from publication');
        $this->assertSame(['floor_area', 'deposit'], $this->keys($component), 'but it is still in the draft, not deleted');
    }

    public function test_only_chosen_terms_contribute_and_removing_one_clears_it(): void
    {
        $user = Actor::factory()->create()->user;
        $component = Livewire::actingAs($user)->test(Composer::class)->call('choose', 'offer')
            ->set('core.description', 'Moving help')->set('core.location_text', 'Valiasr')->set('core.origin_text', 'A')->set('core.quantity', '3');
        $component->call('publish')->assertHasNoErrors();
        $post = ActorProfileIntent::query()->sole();
        $this->assertNull($post->location_text, 'a place nobody chose is not published');
        $this->assertNull($post->origin_text);
        $this->assertNull($post->quantity);

        $component->call('addTerm', 'place')->set('core.location_text', 'Valiasr')->call('publish');
        $this->assertSame('Valiasr', $post->fresh()->location_text);
        $component->call('removeTerm', 'place')->assertSet('core.location_text', null)->call('publish');
        $this->assertNull($post->fresh()->location_text);
    }

    public function test_price_term_follows_the_arrangement_and_stays_off_until_chosen(): void
    {
        $component = $this->composer()->call('choose', 'rent')->call('chooseTopic', 'home');
        $component->assertSet('priceMode', 'ask');
        $component->call('addTerm', 'price')->assertSet('priceMode', 'per_unit')->assertSet('core.cash_basis', 'month');
        $component->set('price', '5,000,000')->set('core.description', 'Flat')->call('publish')->assertHasNoErrors();
        $post = ActorProfileIntent::query()->sole();
        $this->assertSame('cash_only', (string) $post->getRawOriginal('exchange_preference'));
        $this->assertSame('month', (string) $post->getRawOriginal('cash_basis'));
    }

    public function test_a_topic_hint_appears_from_the_words_but_is_never_applied_silently(): void
    {
        $component = $this->composer()->call('choose', 'offer')->set('core.description', 'Selling my used car, Peugeot 206');
        $component->assertSeeHtml('data-topic-hint')->assertSet('topic', '');
        $component->call('chooseTopic', 'vehicle')->assertDontSeeHtml('data-topic-hint');
        app()->setLocale('fa');
        $this->composer()->call('choose', 'offer')->set('core.description', 'گوشی آیفون دست دوم')->assertSeeHtml('data-topic-hint');
    }

    public function test_toggles_and_choices_filter_the_directory_and_present_in_the_viewers_language(): void
    {
        $owner = Actor::factory()->create()->user;
        $this->publishSurfaces($owner, ['market']);
        $component = Livewire::actingAs($owner)->test(Composer::class)->call('choose', 'sell')->call('chooseTopic', 'home')
            ->set('core.title', 'Flat with parking')->set('core.visibility', 'authenticated')->call('addField', 'property_type')->call('addField', 'parking');
        $component->set('details.0.value', 'apartment')->set('details.1.value', true);
        $component->call('publish')->assertHasNoErrors();
        $post = ActorProfileIntent::query()->sole();

        $viewer = Actor::factory()->create()->user;
        $this->publishSurfaces($viewer, ['market']);
        Livewire::actingAs($viewer)->test(Directory::class)->set('detail', 'parking')->set('detailText', '1')->assertSee('Flat with parking');
        Livewire::actingAs($viewer)->test(Directory::class)->set('detail', 'elevator')->set('detailText', '1')->assertDontSee('Flat with parking');
        Livewire::actingAs($viewer)->test(Directory::class)->set('detail', 'property_type')->set('detailText', 'land')->assertDontSee('Flat with parking');

        app()->setLocale('fa');
        $shown = app(IntentPresentation::class)->published($post->fresh(), $viewer);
        $this->assertContains('آپارتمان', array_column($shown['details'], 'value'));
        $this->assertContains('نوع ملک', array_column($shown['details'], 'label'), 'canonical labels follow the viewer, not the author');
    }

    public function test_private_fields_stay_out_of_search_and_templates_keep_structure_without_values(): void
    {
        $user = Actor::factory()->create()->user;
        $component = Livewire::actingAs($user)->test(Composer::class)->call('choose', 'offer')->set('core.description', 'Phone')
            ->call('addField', 'brand')->call('addField', 'color')->set('details.0.value', 'Acme')->set('details.1.value', 'Secret blue')
            ->call('toggleDetailPrivacy', 1);
        $component->call('publish')->assertHasNoErrors();
        $version = ActorProfileIntent::query()->sole()->publishedVersion;
        $this->assertDatabaseHas('intent_search_values', ['intent_version_id' => $version->id, 'canonical_key' => 'brand']);
        $this->assertDatabaseMissing('intent_search_values', ['intent_version_id' => $version->id, 'canonical_key' => 'color']);

        $component->set('groupName', 'Phone basics')->call('saveTemplate', 'group')->assertHasNoErrors();
        $component->set('templateName', 'Phone post')->call('chooseTopic', 'device')->call('addTerm', 'price')->call('saveTemplate', 'composition');
        $this->assertStringNotContainsString('Acme', json_encode(IntentDetailTemplate::query()->get()->toArray()));
        $this->assertStringNotContainsString('Secret blue', json_encode(IntentDetailTemplate::query()->get()->toArray()));
        $this->assertSame(['group', 'composition'], IntentDetailTemplate::query()->orderBy('id')->pluck('kind')->all());
        $this->assertSame(['price'], IntentDetailTemplate::query()->where('kind', 'composition')->sole()->terms);
    }

    public function test_applying_a_template_adds_only_what_is_missing_and_templates_can_be_renamed_and_deleted(): void
    {
        $user = Actor::factory()->create()->user;
        $component = Livewire::actingAs($user)->test(Composer::class)->call('choose', 'offer')->call('addField', 'brand')->call('addField', 'model')
            ->set('groupName', 'Brand and model')->call('saveTemplate', 'group');
        $template = IntentDetailTemplate::query()->sole();

        $fresh = Livewire::actingAs($user)->test(Composer::class)->call('choose', 'offer')->call('addField', 'brand')->set('details.0.value', 'Keep me');
        $fresh->call('applyTemplate', $template->uuid);
        $this->assertSame(['brand', 'model'], $this->keys($fresh));
        $this->assertSame('Keep me', $fresh->get('details.0.value'), 'existing answers are never overwritten');

        $fresh->call('startRename', $template->uuid)->set('renameTo', 'Renamed')->call('saveRename');
        $this->assertSame('Renamed', $template->fresh()->name);
        $stranger = Actor::factory()->create()->user;
        Livewire::actingAs($stranger)->test(Composer::class)->call('deleteTemplate', $template->uuid)->assertForbidden();
        $this->actingAs($user);
        Livewire::actingAs($user)->test(Composer::class)->call('deleteTemplate', $template->uuid);
        $this->assertDatabaseCount('intent_detail_templates', 0);
        unset($component);
    }

    public function test_custom_fields_work_immediately_and_can_be_saved_for_reuse(): void
    {
        $user = Actor::factory()->create()->user;
        $component = Livewire::actingAs($user)->test(Composer::class)->call('choose', 'offer')->set('core.description', 'Handmade rugs')
            ->set('customLabel', 'Knots per metre')->call('createCustom');
        $this->assertSame('Knots per metre', $component->get('details.0.label'));
        $component->set('details.0.value', 'Fine')->call('saveField', 0)->call('publish')->assertHasNoErrors();
        $this->assertSame('Knots per metre', ActorProfileIntent::query()->sole()->publishedVersion->snapshot['details'][0]['label']);
        $this->assertSame('field', IntentDetailTemplate::query()->sole()->kind);
        $this->assertDatabaseMissing('intent_search_values', ['canonical_key' => null]);
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
}
