<?php

namespace Tests\Feature\Market;

use App\Livewire\Intents\Composer;
use App\Models\Actor;
use App\Models\ActorProfile;
use App\Models\ActorProfileIntent;
use App\Models\IntentDetailTemplate;
use App\ProfileIntentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class ComposerInteractionTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    public function test_minimal_description_saves_privately_then_publishes_the_same_record(): void
    {
        $user = Actor::factory()->create()->user;
        $component = Livewire::actingAs($user)->test(Composer::class)
            ->set('core.description', 'Someone to repair a window')
            ->call('saveDraft')->assertHasNoErrors();
        $post = ActorProfileIntent::query()->sole();
        $this->assertSame(ProfileIntentStatus::Draft, $post->status);
        $this->assertNull($post->published_version_id);
        $component->call('publish')->assertHasNoErrors();
        $this->assertSame(ProfileIntentStatus::Active, $post->fresh()->status);
        $this->assertNotNull($post->fresh()->published_version_id);
        $this->assertDatabaseCount('actor_profile_intents', 1);
    }

    public function test_empty_post_does_not_create_a_record(): void
    {
        Livewire::actingAs(Actor::factory()->create()->user)->test(Composer::class)
            ->call('saveDraft')->assertHasErrors('core.description');
        $this->assertDatabaseCount('actor_profile_intents', 0);
    }

    public function test_saved_draft_can_change_intention_without_losing_text_and_details(): void
    {
        $component = Livewire::actingAs(Actor::factory()->create()->user)->test(Composer::class)
            ->set('core.description', 'Repairing windows')
            ->set('details', [['label' => 'Materials', 'type' => 'text', 'value' => 'Wood', 'visibility' => 'same']])
            ->call('saveDraft')->call('choose', 'service')->call('saveDraft')->assertHasNoErrors();
        $post = ActorProfileIntent::query()->sole();
        $this->assertSame('offer', $post->kind->value);
        $this->assertSame('Repairing windows', $post->working_copy['core']['description']);
        $this->assertSame('Wood', $post->working_copy['details'][0]['payload']['value']);
        $component->call('publish')->assertHasNoErrors();
    }

    public function test_composer_translation_keys_have_parity_in_all_four_languages(): void
    {
        $keys = fn ($locale): array => array_keys(Arr::dot(require lang_path($locale.'/composer.php')));
        $expected = $keys('en');
        foreach (['fa', 'ar', 'zh_CN'] as $locale) {
            $this->assertSame($expected, $keys($locale));
        }
    }

    public function test_template_saves_only_shape_and_can_be_saved_before_values_are_entered(): void
    {
        Livewire::actingAs(Actor::factory()->create()->user)->test(Composer::class)
            ->set('details', [['label' => 'Notes', 'type' => 'text', 'value' => 'secret sentinel', 'visibility' => 'private'],
                ['label' => 'Additional count', 'type' => 'number', 'value' => '', 'visibility' => 'same']])
            ->set('templateName', 'My fields')->call('saveTemplate')->assertHasNoErrors();
        $fields = IntentDetailTemplate::query()->sole()->fields;
        $this->assertArrayNotHasKey('payload', $fields[0]);
        $this->assertStringNotContainsString('secret sentinel', json_encode($fields));
        $this->assertSame('number', $fields[1]['type']);
    }

    public function test_legacy_usd_price_is_preserved_when_editing_description(): void
    {
        $actor = Actor::factory()->create();
        $post = ActorProfileIntent::factory()->create(['actor_profile_id' => ActorProfile::factory()->create(['actor_id' => $actor->id])->id,
            'created_by_actor_id' => $actor->id, 'cash_min' => '50.00', 'cash_max' => '100.00', 'currency_code' => 'USD']);
        Livewire::actingAs($actor->user)->test(Composer::class, ['intent' => $post])
            ->set('core.description', 'Edited description')->call('publish')->assertHasNoErrors();
        $this->assertSame('USD', $post->fresh()->currency_code);
        $this->assertSame('50.00', $post->fresh()->cash_min);
        $this->assertSame('100.00', $post->fresh()->cash_max);
    }

    public function test_composer_routes_render_for_owner_and_refuse_others(): void
    {
        $owner = Actor::factory()->create()->user;
        $this->publishSurfaces($owner, ['market']);
        $this->actingAs($owner)->get(route('intents.compose'))->assertOk();
        Livewire::actingAs($owner)->test(Composer::class)->set('core.description', 'Need a ladder')->call('saveDraft');
        $post = ActorProfileIntent::query()->sole();
        $this->actingAs($owner)->get(route('intents.compose.edit', $post))->assertOk();
        $other = Actor::factory()->create()->user;
        $this->publishSurfaces($other, ['market']);
        $this->actingAs($other)->get(route('intents.compose.edit', $post))->assertForbidden();
        $this->actingAs($owner)->get(route('intents.create'))->assertOk();
    }
}
