<?php

namespace Tests\Feature\Market;

use App\Livewire\Intents\Composer;
use App\Models\Actor;
use App\Models\IntentDetailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class PersonalGroupsTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    public function test_fields_groups_and_templates_are_three_separate_things(): void
    {
        $user = $this->user();
        $component = $this->composer($user)->call('choose', 'offer')->call('chooseTopic', 'vehicle')->call('addField', 'brand')->call('addField', 'model')->set('core.description', 'Car');
        $component->set('groupName', 'Car identity')->call('saveTemplate', 'group')->set('templateName', 'Used car post')->call('saveTemplate', 'composition');
        $component->call('openPicker');
        $html = $component->html();
        $this->assertStringNotContainsString("saveTemplate('composition')", $this->pickerHtml($html), 'the picker only finds and creates fields');
        $this->assertStringNotContainsString("saveTemplate('group')", $this->pickerHtml($html));
        $this->assertSame(['group', 'composition'], IntentDetailTemplate::query()->orderBy('id')->pluck('kind')->all());
        $component->assertSeeHtml('data-save-template')->assertSee(__('composer.save_draft'))->assertSee(__('composer.save_as_template'));
        $composition = IntentDetailTemplate::query()->where('kind', 'composition')->sole();
        $this->assertSame(['side' => 'offer', 'arrangement' => 'ownership_transfer'], $composition->context);
        $this->assertSame('vehicle', $composition->topic);
        $this->assertNull(IntentDetailTemplate::query()->where('kind', 'group')->sole()->topic);
    }

    public function test_a_field_joins_a_personal_group_by_reference_and_groups_can_be_arranged_and_nested(): void
    {
        $user = $this->user();
        $component = $this->composer($user)->call('choose', 'offer')->call('addField', 'brand')->call('addField', 'model')->call('addField', 'color');
        $component->set('groupName', 'Identity')->call('addToGroup', 0);
        $identity = IntentDetailTemplate::query()->where('name', 'Identity')->sole();
        $this->assertSame(['brand'], array_column($identity->fields, 'canonical_key'));
        $component->call('addToGroup', 1, $identity->uuid)->call('addToGroup', 1, $identity->uuid)->call('addToGroup', 2, $identity->uuid);
        $this->assertSame(['brand', 'model', 'color'], array_column($identity->fresh()->fields, 'canonical_key'), 'added once each, no copies of the definition');

        $component->call('moveInGroup', $identity->uuid, 2, -1)->call('removeFromGroup', $identity->uuid, 0);
        $this->assertSame(['color', 'model'], array_column($identity->fresh()->fields, 'canonical_key'));

        $component->set('groupName', 'Details')->call('addToGroup', 0);
        $details = IntentDetailTemplate::query()->where('name', 'Details')->sole();
        $component->call('nestGroup', $details->uuid, $identity->uuid);
        $this->assertSame($identity->id, $details->fresh()->parent_id);
        $component->set('groupName', 'Third')->call('addToGroup', 0);
        $third = IntentDetailTemplate::query()->where('name', 'Third')->sole();
        $this->composer($user)->call('choose', 'offer')->call('nestGroup', $third->uuid, $details->uuid)->assertStatus(422);
        $this->assertNull($third->fresh()->parent_id, 'one level of nesting only');
        $this->composer($user)->call('choose', 'offer')->call('nestGroup', $identity->uuid, $details->uuid)->assertStatus(422);
        $this->assertNull($identity->fresh()->parent_id, 'a group that holds groups cannot be nested');

        $fresh = $this->composer($user)->call('choose', 'offer')->call('applyTemplate', $identity->uuid);
        $this->assertEqualsCanonicalizing(['color', 'model', 'brand'], $this->keys($fresh), 'a group applies its nested groups too');

        $component->call('deleteTemplate', $identity->uuid);
        $this->assertNull($details->fresh()->parent_id, 'nested groups survive the deletion of their parent');
    }

    public function test_groups_belong_to_their_owner_alone(): void
    {
        $owner = $this->user();
        $this->composer($owner)->call('choose', 'offer')->call('addField', 'brand')->set('groupName', 'Mine')->call('addToGroup', 0);
        $group = IntentDetailTemplate::query()->sole();
        $intruder = $this->user();
        foreach (['applyTemplate', 'deleteTemplate', 'startRename'] as $call) {
            $this->composer($intruder)->call('choose', 'offer')->call($call, $group->uuid)->assertForbidden();
        }
        $this->composer($intruder)->call('choose', 'offer')->call('nestGroup', $group->uuid, '')->assertForbidden();
        $this->assertDatabaseCount('intent_detail_templates', 1);
    }

    public function test_a_composition_template_keeps_structure_order_and_privacy_but_never_answers_and_never_overwrites(): void
    {
        $user = $this->user();
        $maker = $this->composer($user)->call('choose', 'offer')->call('chooseTopic', 'device')->set('core.description', 'My very private phone story')
            ->call('addField', 'brand')->call('addField', 'color')->call('addTerm', 'price')->set('details.0.value', 'Secret brand 777')
            ->call('toggleDetailPrivacy', 1)->set('templateName', 'Phone post')->call('saveTemplate', 'composition');
        $dump = json_encode(IntentDetailTemplate::query()->get()->toArray());
        foreach (['Secret brand 777', 'My very private phone story'] as $secret) {
            $this->assertStringNotContainsString($secret, $dump);
        }
        $this->assertSame(['brand', 'color'], array_column(IntentDetailTemplate::query()->sole()->fields, 'canonical_key'), 'order is kept');
        $this->assertSame('private', IntentDetailTemplate::query()->sole()->fields[1]['visibility'], 'privacy defaults are kept');
        unset($maker);

        $template = IntentDetailTemplate::query()->sole();
        $next = $this->composer($user)->call('choose', 'offer')->call('addField', 'brand')->set('details.0.value', 'Typed already');
        $next->assertSee(__('composer.use_template'))->assertSeeHtml('data-use-template');
        $next->call('applyTemplate', $template->uuid);
        $this->assertSame('Typed already', $next->get('details.0.value'), 'answers are never overwritten');
        $this->assertSame(['brand', 'color'], $this->keys($next));
        $this->assertSame('device', $next->get('topic'));
        $this->assertSame('private', $next->get('details.1.visibility'));
        $this->assertContains('price', $next->get('terms'));
        $this->assertStringContainsString(__('composer.confirm_topic'), $this->composer($user)->call('choose', 'offer')->call('chooseTopic', 'vehicle')->html(), 'a different topic asks first');

        $need = $this->composer($user)->call('choose', 'need');
        $need->assertDontSeeHtml('data-use-template');
    }

    public function test_the_library_is_navigated_by_domain_and_group_and_offers_suggestions_in_place(): void
    {
        $user = $this->user();
        $html = $this->composer($user)->call('choose', 'offer')->call('openPicker')->html();
        $this->assertStringContainsString('data-library-tree', $html);
        $this->assertStringContainsString('data-suggest-group', $html);
        $this->assertStringContainsString('data-suggest-library', $html);
        foreach (['Vehicles', 'Make, model and year', 'Condition and use'] as $name) {
            $this->assertStringContainsString($name, $html);
        }
        app()->setLocale('fa');
        $fa = $this->composer($user)->call('choose', 'offer')->call('openPicker')->html();
        $this->assertStringContainsString('برند، مدل و سال', $fa);
        $this->assertStringContainsString('پیشنهاد بهبود', $fa);
    }

    private function user(): User
    {
        $user = Actor::factory()->create()->user;
        $this->publishSurfaces($user, ['market']);

        return $user;
    }

    private function composer(User $user): Testable
    {
        return Livewire::actingAs($user)->test(Composer::class);
    }

    private function pickerHtml(string $html): string
    {
        $start = strpos($html, 'data-composer-picker');

        return $start === false ? '' : substr($html, $start);
    }

    /** @return list<string> */
    private function keys(Testable $component): array
    {
        return array_values(array_filter(array_column($component->get('details'), 'canonical_key')));
    }
}
