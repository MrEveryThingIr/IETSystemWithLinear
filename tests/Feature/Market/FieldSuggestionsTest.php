<?php

namespace Tests\Feature\Market;

use App\Actions\Market\ApplyFieldChange;
use App\Actions\Market\ProposeFieldChange;
use App\Actions\Market\ReviewIntentFields;
use App\Livewire\Intents\Composer;
use App\Livewire\Market\FieldSuggestions;
use App\Livewire\Market\SuggestImprovement;
use App\Livewire\Platform\FieldProposals;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\IntentFieldDefinition;
use App\Models\IntentFieldProposal;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\PlatformRole;
use App\Support\Market\IntentDetailCatalog;
use App\Support\Market\IntentFieldLibrary;
use App\Support\Market\IntentFieldTaxonomy;
use App\Support\Market\IntentProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class FieldSuggestionsTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    public function test_a_person_suggests_from_the_field_menu_without_leaving_and_without_any_answer(): void
    {
        $user = $this->user();
        Livewire::actingAs($user)->test(Composer::class)->call('choose', 'offer')->call('chooseTopic', 'vehicle')->set('core.description', 'Private words about my car')
            ->call('addField', 'insurance_months')->set('details.0.value', '7')->assertSeeHtml('data-suggest-field')->assertSeeLivewire(SuggestImprovement::class);
        Livewire::actingAs($user)->test(SuggestImprovement::class)
            ->call('show', 'insurance_months', 'vehicle', 'offer', 'ownership_transfer', null, ['insurance_months', 'brand'])
            ->assertSet('open', true)->assertSeeHtml('data-suggestion-modal')
            ->call('pick', 'words')->assertSet('type', 'label')->set('data.text', 'Months left on third-party insurance')->set('explanation', 'Clearer in Persian too')
            ->call('submit')->assertSet('step', 'done')->assertSeeHtml('data-suggestion-done');
        $proposal = IntentFieldProposal::query()->sole();
        $this->assertSame('label', $proposal->change_type);
        $this->assertSame('insurance_months', $proposal->target_key);
        $this->assertSame(['en' => 'Months left on third-party insurance'], $proposal->payload['labels']);
        $this->assertSame('vehicle', $proposal->context['topic']);
        $this->assertSame('Clearer in Persian too', $proposal->explanation);
        $dump = json_encode($proposal->toArray());
        $this->assertStringNotContainsString('Private words', $dump);
        $this->assertStringNotContainsString('"7"', $dump, 'no answer ever travels with a suggestion');
    }

    public function test_every_kind_of_suggestion_is_validated_and_ordinary_choices_stay_simple(): void
    {
        $user = $this->user();
        $propose = fn (string $type, ?string $key, array $data, array $context = []) => app(ProposeFieldChange::class)->execute($user, $type, $key, $data, $context);
        $this->assertSame('unit', $propose('unit', 'weight_kg', ['unit' => 'g'])->change_type);
        $this->assertSame('data_type', $propose('data_type', 'size', ['type' => 'number', 'unit' => 'cm'])->change_type);
        $this->assertSame(['add' => ['hybrid_plug_in'], 'remove' => ['gas']], collect($propose('options', 'fuel_type', ['add' => 'hybrid_plug_in', 'remove' => ['gas', 'unknown']])->payload)->only(['add', 'remove'])->all());
        $this->assertSame('aliases', $propose('aliases', 'brand', ['aliases' => ['en' => 'maker, marque']])->change_type);
        $this->assertSame('group', $propose('group', 'parking', ['action' => 'add', 'group_key' => 'vehicle_condition'])->change_type);
        $this->assertSame('group', $propose('group', null, ['action' => 'new', 'group_label' => 'Documents', 'parent_key' => 'vehicle'])->change_type);
        $this->assertSame('applicability', $propose('applicability', 'deposit', ['effect' => 'only_need', 'topic' => 'home'])->change_type);
        $this->assertSame('applicability', $propose('applicability', 'deposit', ['effect' => 'show_when', 'when_key' => 'furnished', 'when_values' => ['1']])->change_type);
        $this->assertSame('duplicate', $propose('duplicate', 'size', ['other_key' => 'dimensions'])->change_type);
        $this->assertSame('validation', $propose('validation', 'rooms', ['message' => 'The error said nothing useful', 'where' => 'validation'])->change_type);
        $this->assertSame('template', $propose('template', null, ['name' => 'Car papers', 'fields' => ['insurance_months', 'previous_owners'], 'parent_key' => 'vehicle'])->change_type);
        $this->assertSame($propose('unit', 'weight_kg', ['unit' => 'g'])->id, IntentFieldProposal::query()->where('change_type', 'unit')->sole()->id, 'repeating the same suggestion is one suggestion');
        foreach ([['label', 'brand', []], ['options', 'brand', ['add' => 'x']], ['group', 'brand', ['action' => 'add', 'group_key' => 'nope']], ['duplicate', 'brand', ['other_key' => 'brand']],
            ['unit', 'unknown_key', ['unit' => 'g']], ['applicability', 'rooms', ['effect' => 'show_when']], ['template', null, ['name' => 'x', 'fields' => ['brand']]], ['nonsense', 'brand', []]] as [$type, $key, $data]) {
            try {
                $propose($type, $key, $data);
                $this->fail("$type should be refused");
            } catch (ValidationException|HttpException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_people_see_only_their_own_suggestions_and_the_reviewers_answer(): void
    {
        [$mine, $other, $admin] = [$this->user(), $this->user(), $this->admin()];
        $first = app(ProposeFieldChange::class)->execute($mine, 'label', 'brand', ['labels' => ['en' => 'Make']], ['topic' => 'vehicle']);
        app(ProposeFieldChange::class)->execute($other, 'unit', 'weight_kg', ['unit' => 'g']);
        app(ReviewIntentFields::class)->reject($admin, $first, 'Make is already a synonym');
        Livewire::actingAs($mine)->test(FieldSuggestions::class)->assertSee('Better name')->assertSee('Not applied')->assertSee('Make is already a synonym')->assertDontSee('Unit');
        $this->actingAs($mine)->get(route('intents.field-suggestions'))->assertOk();
    }

    public function test_approval_publishes_wording_as_a_new_version_and_published_posts_keep_theirs(): void
    {
        $admin = $this->admin();
        $owner = $this->user();
        $post = $this->publishedBrandPost($owner);
        $before = $post->publishedVersion->snapshot['details'][0]['label'];

        $label = $this->proposal('label', 'brand', ['labels' => ['en' => 'Manufacturer', 'fa' => 'سازنده', 'ar' => 'الشركة المصنعة', 'zh_CN' => '制造商']]);
        app(ReviewIntentFields::class)->approve($admin, $label, []);
        $this->assertSame('Manufacturer', IntentDetailCatalog::label('brand', 'en'));
        $this->assertSame('published', $label->fresh()->status);
        $this->assertSame($before, $post->fresh()->publishedVersion->snapshot['details'][0]['label'], 'the stored version keeps its words');

        $alias = $this->proposal('aliases', 'brand', ['aliases' => ['en' => ['marque', 'maker']]]);
        app(ReviewIntentFields::class)->approve($admin, $alias, []);
        $this->assertContains('marque', IntentDetailCatalog::aliases('brand', 'en'));
        $this->assertContains('brand', IntentFieldLibrary::search('marque'));
        $this->assertSame(2, IntentFieldDefinition::query()->where('field_key', 'brand')->count());
        $this->assertSame('text', IntentDetailCatalog::all()['brand']['type']);
    }

    public function test_choices_can_be_extended_or_hidden_without_invalidating_old_values(): void
    {
        $admin = $this->admin();
        $change = $this->proposal('options', 'fuel_type', ['add' => ['lpg_hybrid'], 'remove' => ['gas']]);
        app(ReviewIntentFields::class)->approve($admin, $change, []);
        $this->assertContains('lpg_hybrid', IntentDetailCatalog::all()['fuel_type']['options']);
        $this->assertContains('gas', IntentDetailCatalog::all()['fuel_type']['options'], 'old values stay valid');
        $this->assertNotContains('gas', IntentDetailCatalog::selectableOptions('fuel_type'));
        $this->assertContains('gas', IntentDetailCatalog::selectableOptions('fuel_type', 'gas'), 'an already chosen value stays visible');

        $user = $this->user();
        $component = Livewire::actingAs($user)->test(Composer::class)->call('choose', 'offer')->set('core.description', 'Old car')->call('addField', 'fuel_type');
        $component->set('details.0.value', 'gas')->call('publish')->assertHasNoErrors();
        $this->assertSame('gas', ActorProfileIntent::query()->sole()->publishedVersion->snapshot['details'][0]['payload']['value']);
    }

    public function test_a_different_type_or_unit_becomes_a_successor_and_the_old_field_keeps_meaning(): void
    {
        $admin = $this->admin();
        $owner = $this->user();
        $component = Livewire::actingAs($owner)->test(Composer::class)->call('choose', 'offer')->set('core.description', 'Heavy parcel')->call('addField', 'weight_kg');
        $component->set('details.0.value', '2500')->call('publish')->assertHasNoErrors();
        $post = ActorProfileIntent::query()->sole();

        $change = $this->proposal('unit', 'weight_kg', ['unit' => 'g']);
        try {
            app(ReviewIntentFields::class)->approve($admin, $change, ['new_key' => 'brand']);
            $this->fail('A taken key cannot be a successor');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
        app(ReviewIntentFields::class)->approve($admin, $change->fresh(), ['new_key' => 'weight_g']);
        $this->assertSame('g', IntentDetailCatalog::all()['weight_g']['unit']);
        $this->assertArrayHasKey('weight_kg', IntentDetailCatalog::all(), 'the old key still resolves');
        $this->assertArrayNotHasKey('weight_kg', IntentDetailCatalog::offered());
        $this->assertArrayHasKey('weight_g', IntentDetailCatalog::offered());
        $this->assertSame('kg', IntentDetailCatalog::all()['weight_kg']['unit'], 'its own meaning is unchanged');
        $this->assertSame('weight_g', IntentDetailCatalog::all()['weight_kg']['superseded_by']);
        $this->assertSame('2500', (string) $post->fresh()->publishedVersion->snapshot['details'][0]['payload']['value']);
        $reopened = Livewire::actingAs($owner)->test(Composer::class, ['intent' => $post->fresh()]);
        $reopened->set('core.description', 'Heavy parcel, edited')->call('publish')->assertHasNoErrors();
    }

    public function test_duplicates_are_merged_by_words_and_the_taxonomy_is_versioned_and_shared_by_reference(): void
    {
        $admin = $this->admin();
        $duplicate = $this->proposal('duplicate', 'size', ['other_key' => 'dimensions']);
        app(ReviewIntentFields::class)->approve($admin, $duplicate, []);
        $this->assertArrayNotHasKey('size', IntentDetailCatalog::offered());
        $this->assertContains('size label', array_map('mb_strtolower', IntentDetailCatalog::aliases('dimensions', 'en')));

        $before = IntentFieldTaxonomy::fieldsOf('vehicle_condition');
        $this->assertNotContains('parking', $before);
        app(ReviewIntentFields::class)->approve($admin, $this->proposal('group', 'parking', ['action' => 'add', 'group_key' => 'vehicle_condition']), []);
        $this->assertContains('parking', IntentFieldTaxonomy::fieldsOf('vehicle_condition'));
        $this->assertContains('parking', IntentFieldTaxonomy::fieldsOf('property_amenities'), 'one field, several groups, one definition');

        app(ReviewIntentFields::class)->approve($admin, $this->proposal('group', 'parking', ['action' => 'remove', 'group_key' => 'vehicle_condition']), []);
        $this->assertNotContains('parking', IntentFieldTaxonomy::fieldsOf('vehicle_condition'));
        app(ReviewIntentFields::class)->approve($admin, $this->proposal('group', 'color', ['action' => 'move', 'group_key' => 'vehicle_condition', 'to_group_key' => 'vehicle_identity']), []);
        $this->assertContains('color', IntentFieldTaxonomy::fieldsOf('vehicle_identity'));
        $this->assertNotContains('color', IntentFieldTaxonomy::fieldsOf('vehicle_condition'));

        $new = $this->proposal('group', null, ['action' => 'new', 'group_label' => 'Documents', 'parent_key' => 'vehicle']);
        app(ReviewIntentFields::class)->approve($admin, $new, ['group_key' => 'vehicle_documents', 'labels' => ['en' => 'Documents', 'fa' => 'مدارک']]);
        $this->assertSame('Documents', IntentFieldTaxonomy::groupLabel('vehicle_documents', 'en'));
        $shared = $this->proposal('template', null, ['name' => 'Papers', 'fields' => ['insurance_months', 'previous_owners'], 'parent_key' => 'vehicle_documents']);
        app(ReviewIntentFields::class)->approve($admin, $shared, ['group_key' => 'vehicle_papers', 'labels' => ['en' => 'Papers']]);
        $this->assertSame(['insurance_months', 'previous_owners'], IntentFieldTaxonomy::fieldsOf('vehicle_papers'));
        $this->assertContains('insurance_months', IntentFieldTaxonomy::fieldsOf('vehicle_documents'), 'a nested group is part of its parent');
        $tree = IntentFieldTaxonomy::tree()['vehicle']['groups'];
        $this->assertContains('vehicle_documents', array_column($tree, 'key'));
    }

    public function test_approved_applicability_rules_narrow_recommendations_and_publication_not_history(): void
    {
        $admin = $this->admin();
        $owner = $this->user();
        $component = Livewire::actingAs($owner)->test(Composer::class)->call('choose', 'rent')->call('chooseTopic', 'home')->set('core.description', 'Flat')->call('addField', 'furnished');
        $component->set('details.0.value', true)->call('publish')->assertHasNoErrors();
        $post = ActorProfileIntent::query()->sole();
        $this->assertContains('furnished', array_column($post->publishedVersion->snapshot['details'], 'canonical_key'));

        $this->assertContains('floor', IntentProfiles::fields('home', 'need', 'ownership_transfer', [])['suggested']);
        app(ReviewIntentFields::class)->approve($admin, $this->proposal('applicability', 'floor', ['effect' => 'only_offer', 'topic' => 'home']), []);
        $this->assertNotContains('floor', IntentProfiles::fields('home', 'need', 'ownership_transfer', [])['suggested'], 'no longer recommended to Needs');
        $this->assertContains('floor', IntentProfiles::fields('home', 'sell', 'ownership_transfer', [])['suggested']);

        app(ReviewIntentFields::class)->approve($admin, $this->proposal('applicability', 'furnished', ['effect' => 'hide', 'topic' => 'home']), []);
        $this->assertContains('furnished', IntentProfiles::inapplicable('home', 'rent', 'temporary_use', ['furnished' => true]));
        $this->assertContains('furnished', array_column($post->fresh()->publishedVersion->snapshot['details'], 'canonical_key'), 'history is not rewritten');
        $reopened = Livewire::actingAs($owner)->test(Composer::class, ['intent' => $post->fresh()]);
        $reopened->set('core.description', 'Flat v2')->call('publish')->assertHasNoErrors();
        $this->assertNotContains('furnished', array_column($post->fresh()->publishedVersion->snapshot['details'], 'canonical_key'), 'new versions leave out what no longer applies');

        app(ReviewIntentFields::class)->approve($admin, $this->proposal('applicability', 'rooms', ['effect' => 'show_when', 'topic' => 'home', 'condition' => ['only' => ['property_type' => ['apartment']]]]), []);
        $this->assertNotContains('rooms', [...IntentProfiles::fields('home', 'sell', 'ownership_transfer', ['property_type' => 'land'])['essential']]);
        $this->assertContains('rooms', IntentProfiles::fields('home', 'sell', 'ownership_transfer', ['property_type' => 'apartment'])['essential']);
    }

    public function test_advisory_suggestions_are_accepted_but_change_nothing_and_only_reviewers_can_decide(): void
    {
        $admin = $this->admin();
        $advice = $this->proposal('validation', 'rooms', ['message' => 'Say what is wrong', 'where' => 'validation']);
        $this->assertNull(app(ReviewIntentFields::class)->approve($admin, $advice, []));
        $this->assertSame('approved', $advice->fresh()->status);
        $this->assertSame(0, IntentFieldDefinition::query()->count());

        $pending = $this->proposal('label', 'brand', ['labels' => ['en' => 'Maker']]);
        $this->expectException(HttpException::class);
        app(ReviewIntentFields::class)->approve($this->user(), $pending, []);
    }

    public function test_the_review_page_shows_original_proposal_context_compatibility_and_applies_it(): void
    {
        $admin = $this->admin();
        $this->publishSurfaces($admin, ['field-review']);
        $proposer = $this->user();
        $proposal = app(ProposeFieldChange::class)->execute($proposer, 'label', 'insurance_months', ['labels' => ['en' => 'Months of cover']], ['topic' => 'vehicle', 'intention' => 'offer'], 'Clearer for sellers');
        $page = Livewire::actingAs($admin)->test(FieldProposals::class)->assertSee('Better name')->call('open', $proposal->uuid)
            ->assertSeeHtml('data-original')->assertSeeHtml('data-proposed')->assertSeeHtml('data-compat')->assertSee('Clearer for sellers')->assertSee('vehicle')
            ->assertSee('Remaining third-party insurance')->assertSee('Months of cover');
        $page->set('form.payload.labels.fa', 'ماه‌های پوشش')->set('form.payload.labels.ar', 'أشهر التغطية')->set('form.payload.labels.zh_CN', '保障月数')->call('approve')->assertHasNoErrors();
        $this->assertSame('published', $proposal->fresh()->status);
        $this->assertSame('Months of cover', IntentDetailCatalog::label('insurance_months', 'en'));
        $this->assertSame('ماه‌های پوشش', IntentDetailCatalog::label('insurance_months', 'fa'));
        Livewire::actingAs($proposer)->test(FieldSuggestions::class)->assertSee('Applied');
    }

    public function test_retiring_a_built_in_hides_it_from_the_library_but_old_posts_still_resolve(): void
    {
        $admin = $this->admin();
        app(ReviewIntentFields::class)->retire($admin, 'body_type');
        $this->assertArrayHasKey('body_type', IntentDetailCatalog::all());
        $this->assertArrayNotHasKey('body_type', IntentDetailCatalog::offered());
        app(ApplyFieldChange::class);
        $this->assertNotContains('body_type', IntentFieldLibrary::search('car type'));
    }

    private function user(): User
    {
        $user = Actor::factory()->create()->user;
        $this->publishSurfaces($user, ['market']);

        return $user;
    }

    private function admin(): User
    {
        $actor = Actor::factory()->create();
        PlatformAccessGrant::factory()->for($actor->user)->create(['role' => PlatformRole::Superadmin]);

        return $actor->user;
    }

    /** @param array<string, mixed> $payload */
    private function proposal(string $type, ?string $key, array $payload): IntentFieldProposal
    {
        return IntentFieldProposal::query()->create(['proposed_by_actor_id' => Actor::factory()->create()->id, 'kind' => 'change', 'change_type' => $type,
            'target_key' => $key, 'payload' => $payload, 'context' => []]);
    }

    private function publishedBrandPost(User $owner): ActorProfileIntent
    {
        $component = Livewire::actingAs($owner)->test(Composer::class)->call('choose', 'offer')->set('core.description', 'Phone')->call('addField', 'brand');
        $component->set('details.0.value', 'Acme')->call('publish')->assertHasNoErrors();

        return ActorProfileIntent::query()->where('publisher_actor_id', $owner->actor->id)->latest('id')->firstOrFail();
    }
}
