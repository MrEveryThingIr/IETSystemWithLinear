<?php

namespace Tests\Feature;

use App\IntentJourneyPreset;
use App\Livewire\Intents\Create;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\ProfileIntentArrangementKind;
use App\ProfileIntentKind;
use App\ProfileIntentSubjectKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IntentJourneyV2Test extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string, string, string}> */
    public static function presetProvider(): array
    {
        return [
            'buy' => ['buy', 'need', 'good', 'ownership_transfer'],
            'sell' => ['sell', 'offer', 'good', 'ownership_transfer'],
            'rent' => ['rent', 'need', 'property', 'temporary_use'],
            'rent-out' => ['rent_out', 'offer', 'property', 'temporary_use'],
            'need-service' => ['need_service', 'need', 'service', 'service'],
            'offer-service' => ['offer_service', 'offer', 'service', 'service'],
            'hire' => ['hire', 'need', 'service', 'service'],
            'find-work' => ['find_work', 'offer', 'service', 'service'],
            'seek-capital' => ['seek_capital', 'need', 'capital', 'financing'],
            'offer-capital' => ['offer_capital', 'offer', 'capital', 'financing'],
            'seek-collaboration' => ['seek_collaboration', 'need', 'collaboration', 'collaboration'],
            'offer-collaboration' => ['offer_collaboration', 'offer', 'collaboration', 'collaboration'],
            'other' => ['other', 'need', 'other', 'other'],
        ];
    }

    #[DataProvider('presetProvider')]
    public function test_real_world_journey_presets_map_to_existing_intent_semantics(
        string $preset,
        string $kind,
        string $subject,
        string $arrangement,
    ): void {
        $actor = Actor::factory()->create();

        Livewire::actingAs($actor->user)
            ->test(Create::class)
            ->call('chooseJourney', $preset)
            ->assertSet('journeyPreset', $preset)
            ->assertSet('kind', $kind)
            ->assertSet('subjectKind', $subject)
            ->assertSet('arrangementKind', $arrangement);

        $journey = IntentJourneyPreset::from($preset);

        $this->assertSame(ProfileIntentKind::from($kind), $journey->kind());
        $this->assertSame(ProfileIntentSubjectKind::from($subject), $journey->subjectKind());
        $this->assertSame(ProfileIntentArrangementKind::from($arrangement), $journey->arrangementKind());
    }

    public function test_sale_path_can_refine_product_to_property_and_still_creates_one_normal_offer(): void
    {
        $actor = Actor::factory()->create();

        Livewire::actingAs($actor->user)
            ->test(Create::class)
            ->call('chooseJourney', 'sell')
            ->call('next')
            ->assertSet('step', 2)
            ->set('subjectKind', 'property')
            ->set('conceptLabel', 'Riverside Lot')
            ->set('title', 'Riverside Lot available')
            ->set('description', 'Property offered for sale or later explicit collaboration discussion.')
            ->set('visibility', 'authenticated')
            ->call('save')
            ->assertHasNoErrors();

        $intent = ActorProfileIntent::query()->sole();

        $this->assertSame(ProfileIntentKind::Offer, $intent->kind);
        $this->assertSame(ProfileIntentSubjectKind::Property, $intent->subject_kind);
        $this->assertSame(ProfileIntentArrangementKind::OwnershipTransfer, $intent->arrangement_kind);
        $this->assertSame('Riverside Lot available', $intent->title);
        $this->assertDatabaseCount('actor_profile_intents', 1);
        $this->assertDatabaseCount('submissions', 0);
        $this->assertDatabaseCount('evaluations', 0);
    }

    public function test_service_and_employment_wording_share_service_intent_semantics_without_new_employment_domain(): void
    {
        $actor = Actor::factory()->create();

        $hire = Livewire::actingAs($actor->user)
            ->test(Create::class)
            ->call('chooseJourney', 'hire')
            ->assertSet('kind', 'need')
            ->assertSet('subjectKind', 'service')
            ->assertSet('arrangementKind', 'service');

        $hire->set('conceptLabel', 'Residential construction')
            ->set('title', 'Construction worker needed')
            ->call('save')
            ->assertHasNoErrors();

        $findWork = Livewire::actingAs($actor->user)
            ->test(Create::class)
            ->call('chooseJourney', 'find_work')
            ->assertSet('kind', 'offer')
            ->assertSet('subjectKind', 'service')
            ->assertSet('arrangementKind', 'service');

        $findWork->set('conceptLabel', 'Electrical work')
            ->set('title', 'Electrician available')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('actor_profile_intents', 2);
        $this->assertSame(
            ['need', 'offer'],
            ActorProfileIntent::query()->orderBy('id')->get()->pluck('kind')->map->value->all(),
        );
    }

    public function test_other_path_keeps_manual_direction_subject_and_arrangement_available(): void
    {
        $actor = Actor::factory()->create();

        Livewire::actingAs($actor->user)
            ->test(Create::class)
            ->call('chooseJourney', 'other')
            ->call('next')
            ->assertSet('step', 2)
            ->set('kind', 'offer')
            ->set('subjectKind', 'good')
            ->set('arrangementKind', 'other')
            ->set('conceptLabel', 'Custom exchange case')
            ->set('title', 'Custom case')
            ->call('save')
            ->assertHasNoErrors();

        $intent = ActorProfileIntent::query()->sole();

        $this->assertSame(ProfileIntentKind::Offer, $intent->kind);
        $this->assertSame(ProfileIntentSubjectKind::Good, $intent->subject_kind);
        $this->assertSame(ProfileIntentArrangementKind::Other, $intent->arrangement_kind);
    }

    public function test_buy_and_rent_paths_only_offer_relevant_subject_choices_on_step_two(): void
    {
        $actor = Actor::factory()->create();

        Livewire::actingAs($actor->user)
            ->test(Create::class)
            ->call('chooseJourney', 'buy')
            ->call('next')
            ->assertSee('Property / real estate')
            ->assertSee('Thing / good')
            ->assertDontSee('Capital / financing');

        Livewire::actingAs($actor->user)
            ->test(Create::class)
            ->call('chooseJourney', 'rent')
            ->call('next')
            ->assertSee('Property / real estate')
            ->assertSee('Thing / good')
            ->assertDontSee('Service / skill');
    }
}
