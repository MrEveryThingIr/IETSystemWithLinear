<?php

namespace Tests\Feature\Market;

use App\Actions\Market\ProposeIntentField;
use App\Actions\Market\ReviewIntentFields;
use App\Livewire\Intents\Composer;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class FieldGovernanceTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    private const LABELS = ['en' => 'Loom type', 'fa' => 'نوع دار', 'ar' => 'نوع النول', 'zh_CN' => '织机类型'];

    public function test_a_custom_field_can_be_suggested_without_sending_any_answer(): void
    {
        $user = Actor::factory()->create()->user;
        $component = Livewire::actingAs($user)->test(Composer::class)->call('choose', 'offer')->set('core.description', 'Rugs')
            ->set('customLabel', 'Loom type')->call('createCustom')->set('details.0.value', 'Private answer 12345');
        $component->call('suggestStandard', 0)->assertSet('notice', __('composer.suggestion_sent'));
        $proposal = IntentFieldProposal::query()->sole();
        $this->assertSame('proposed', $proposal->status);
        $this->assertSame(['en' => 'Loom type'], $proposal->payload['labels']);
        $this->assertStringNotContainsString('Private answer', json_encode($proposal->toArray()));
        $component->call('suggestStandard', 0);
        $this->assertDatabaseCount('intent_field_proposals', 1);
    }

    public function test_a_suggestion_is_validated_and_open_suggestions_are_limited(): void
    {
        $user = Actor::factory()->create()->user;
        try {
            app(ProposeIntentField::class)->execute($user, ['label' => 'Pick', 'type' => 'choice', 'options' => ['one']], 'en');
            $this->fail('A choice needs two options');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('details', $exception->errors());
        }
        $this->expectException(ValidationException::class);
        app(ProposeIntentField::class)->execute($user, ['label' => 'Bad', 'type' => 'reference'], 'en');
    }

    public function test_only_reviewers_can_see_or_use_the_review_tools(): void
    {
        $user = Actor::factory()->create()->user;
        $this->actingAs($user)->get(route('platform.fields'))->assertForbidden();
        $proposal = $this->proposal();
        $this->expectException(HttpException::class);
        app(ReviewIntentFields::class)->approve($user, $proposal, $this->edit('loom_type'));
    }

    public function test_approval_publishes_a_versioned_definition_that_the_library_then_offers(): void
    {
        $admin = $this->admin();
        $proposal = $this->proposal();
        $review = app(ReviewIntentFields::class);
        $review->review($admin, $proposal, ['labels' => self::LABELS]);
        $this->assertSame('reviewed', $proposal->fresh()->status);

        $definition = $review->approve($admin, $proposal->fresh(), $this->edit('loom_type'));
        $this->assertSame(1, $definition->version);
        $this->assertSame('choice', $definition->type);
        $this->assertSame('value_text', $definition->filter, 'the filter column follows the type, never the request');
        $this->assertSame('published', $proposal->fresh()->status);
        $this->assertSame($definition->id, $proposal->fresh()->definition_id);
        $this->assertContains('loom_type', IntentFieldLibrary::search('loom'));
        $this->assertSame('نوع دار', IntentDetailCatalog::label('loom_type', 'fa'));

        $next = $this->proposal(kind: 'change', target: 'loom_type');
        $second = $review->approve($admin, $next, [...$this->edit('loom_type'), 'labels' => [...self::LABELS, 'en' => 'Type of loom']]);
        $this->assertSame(2, $second->version);
        $this->assertSame('Type of loom', IntentDetailCatalog::label('loom_type', 'en'));
        $this->assertSame('retired', IntentFieldDefinition::query()->where('field_key', 'loom_type')->where('version', 1)->sole()->status, 'history stays, superseded');
    }

    public function test_published_fields_work_in_a_post_and_keep_working_after_retirement(): void
    {
        $admin = $this->admin();
        $review = app(ReviewIntentFields::class);
        $review->approve($admin, $this->proposal(), $this->edit('loom_type'));

        $user = Actor::factory()->create()->user;
        $component = Livewire::actingAs($user)->test(Composer::class)->call('choose', 'offer')->set('core.description', 'Rugs')->call('addField', 'loom_type');
        $component->set('details.0.value', 'hand')->call('publish')->assertHasNoErrors();
        $post = ActorProfileIntent::query()->sole();
        $this->assertDatabaseHas('intent_search_values', ['intent_version_id' => $post->published_version_id, 'canonical_key' => 'loom_type', 'value_text' => 'hand']);

        $review->retire($admin, 'loom_type');
        $this->assertNotContains('loom_type', IntentFieldLibrary::search('loom'));
        $reopened = Livewire::actingAs($user)->test(Composer::class, ['intent' => $post->fresh()]);
        $reopened->set('core.description', 'Rugs, edited')->call('publish')->assertHasNoErrors();
        $this->assertSame('hand', $post->fresh()->publishedVersion->snapshot['details'][0]['payload']['value']);
    }

    public function test_review_rejects_taken_keys_missing_translations_and_builtin_meaning_changes(): void
    {
        $admin = $this->admin();
        $review = app(ReviewIntentFields::class);
        try {
            $review->approve($admin, $this->proposal(), $this->edit('brand'));
            $this->fail('A built-in key cannot be reused for a new field');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('key', $exception->errors());
        }
        try {
            $review->approve($admin, $this->proposal(), [...$this->edit('loom_type'), 'labels' => ['en' => 'Loom type']]);
            $this->fail('Four languages are required');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('labels', $exception->errors());
        }
        // A change proposal on a built-in key may improve words but not its type.
        $change = $this->proposal(kind: 'change', target: 'brand', type: 'number');
        $definition = $review->approve($admin, $change, [...$this->edit('brand'), 'labels' => ['en' => 'Make', 'fa' => 'برند', 'ar' => 'ماركة', 'zh_CN' => '品牌']]);
        $this->assertSame('text', $definition->type);
        $this->assertSame('text', IntentDetailCatalog::all()['brand']['type']);
    }

    public function test_duplicates_are_detected_and_rejection_needs_a_note(): void
    {
        $admin = $this->admin();
        $review = app(ReviewIntentFields::class);
        $proposal = $this->proposal(labels: ['en' => 'Mileage'], type: 'number');
        $this->assertContains('mileage_km', $review->duplicates($proposal));
        try {
            $review->reject($admin, $proposal, '  ');
            $this->fail('A reason is required');
        } catch (ValidationException) {
            $this->assertSame('proposed', $proposal->fresh()->status);
        }
        $review->reject($admin, $proposal, 'Already available', 'mileage_km');
        $this->assertSame('rejected', $proposal->fresh()->status);
        $this->assertSame('mileage_km', $proposal->fresh()->duplicate_of);
        $this->expectException(HttpException::class);
        $review->approve($admin, $proposal->fresh(), $this->edit('mileage_two'));
    }

    public function test_the_review_page_works_for_administrators_end_to_end(): void
    {
        $admin = $this->admin();
        $this->publishSurfaces($admin, ['field-review']);
        $proposal = $this->proposal();
        $this->actingAs($admin)->get(route('platform.fields'))->assertOk()->assertSee('Loom type');
        Livewire::actingAs($admin)->test(FieldProposals::class)->call('open', $proposal->uuid)
            ->set('form.key', 'loom_type')->set('form.labels', self::LABELS)->set('form.options', 'hand, machine')->set('form.domains', ['creative'])
            ->call('approve')->assertHasNoErrors();
        $this->assertSame('published', $proposal->fresh()->status);
        Livewire::actingAs($admin)->test(FieldProposals::class)->set('status', 'library')->assertSee('loom_type')->call('retire', 'loom_type');
        $this->assertSame('retired', IntentFieldDefinition::query()->where('field_key', 'loom_type')->sole()->status);
    }

    private function admin(): User
    {
        $actor = Actor::factory()->create();
        PlatformAccessGrant::factory()->for($actor->user)->create(['role' => PlatformRole::Superadmin]);

        return $actor->user;
    }

    /** @param array<string, string> $labels */
    private function proposal(string $kind = 'new', ?string $target = null, array $labels = ['en' => 'Loom type'], string $type = 'choice'): IntentFieldProposal
    {
        return IntentFieldProposal::query()->create(['proposed_by_actor_id' => Actor::factory()->create()->id, 'kind' => $kind, 'target_key' => $target,
            'payload' => ['labels' => $labels, 'type' => $type, 'unit' => null, 'options' => $type === 'choice' ? ['hand', 'machine'] : []]]);
    }

    /** @return array<string, mixed> */
    private function edit(string $key): array
    {
        return ['key' => $key, 'labels' => self::LABELS, 'aliases' => ['en' => 'weaving, rug machine'], 'domains' => ['creative'], 'options' => ['hand', 'machine']];
    }
}
