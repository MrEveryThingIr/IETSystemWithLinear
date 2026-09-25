<?php

namespace Tests\Feature;

use App\Actions\Profile\CreateActorProfileIntent;
use App\Actions\Profile\EnsureActorProfile;
use App\Livewire\Intents\Create;
use App\Livewire\Intents\Directory;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\ProfileIntentArrangementKind;
use App\ProfileIntentExchangePreference;
use App\ProfileIntentKind;
use App\ProfileIntentScheduleKind;
use App\ProfileIntentSubjectKind;
use App\ProfileItemVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IntentDirectoryReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_guided_wizard_records_a_property_need_with_nonbinding_mixed_value_preference(): void
    {
        $actor = Actor::factory()->create();

        Livewire::actingAs($actor->user)
            ->test(Create::class)
            ->call('chooseJourney', 'buy')
            ->set('kind', ProfileIntentKind::Need->value)
            ->set('subjectKind', ProfileIntentSubjectKind::Property->value)
            ->set('conceptLabel', 'Family house')
            ->set('arrangementKind', ProfileIntentArrangementKind::OwnershipTransfer->value)
            ->set('locationText', 'Central district')
            ->set('cashMin', '3000000000')
            ->set('cashMax', '4500000000')
            ->set('currencyCode', 'IRR')
            ->set('cashBasis', 'total')
            ->set('exchangePreference', ProfileIntentExchangePreference::CashPreferredOpenHybrid->value)
            ->set('exchangeNotes', 'Open to part cash plus clearly valued construction services.')
            ->set('title', 'House wanted')
            ->set('description', 'Looking for a family house with negotiable value structure.')
            ->set('scheduleKind', ProfileIntentScheduleKind::Ongoing->value)
            ->set('visibility', ProfileItemVisibility::Authenticated->value)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('intents.index', [
                'highlight' => ActorProfileIntent::query()->sole()->uuid,
            ]));

        $intent = ActorProfileIntent::query()->sole();

        $this->assertSame(ProfileIntentKind::Need, $intent->kind);
        $this->assertSame(ProfileIntentSubjectKind::Property, $intent->subject_kind);
        $this->assertSame(ProfileIntentArrangementKind::OwnershipTransfer, $intent->arrangement_kind);
        $this->assertSame(
            ProfileIntentExchangePreference::CashPreferredOpenHybrid,
            $intent->exchange_preference,
        );
        $this->assertSame('3000000000.00', $intent->cash_min);
        $this->assertSame('4500000000.00', $intent->cash_max);
        $this->assertSame('IRR', $intent->currency_code);
        $this->assertDatabaseCount('submissions', 0);
        $this->assertDatabaseCount('evaluations', 0);
    }

    public function test_explicit_authenticated_intent_is_discoverable_even_when_profile_remains_private(): void
    {
        $owner = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($owner->user);

        app(CreateActorProfileIntent::class)->execute(
            $owner->user,
            $profile,
            ProfileIntentKind::Offer,
            'Electrical work',
            [
                'subject_kind' => ProfileIntentSubjectKind::Service->value,
                'arrangement_kind' => ProfileIntentArrangementKind::Service->value,
                'exchange_preference' => ProfileIntentExchangePreference::DiscussLater->value,
                'title' => 'Residential electrician',
                'description' => 'Electrical installation and repair.',
                'schedule_kind' => ProfileIntentScheduleKind::Ongoing->value,
                'timezone' => 'UTC',
                'round_trip' => false,
                'visibility' => ProfileItemVisibility::Authenticated->value,
            ],
        );

        app(CreateActorProfileIntent::class)->execute(
            $owner->user,
            $profile,
            ProfileIntentKind::Need,
            'Private capital note',
            [
                'subject_kind' => ProfileIntentSubjectKind::Capital->value,
                'arrangement_kind' => ProfileIntentArrangementKind::Financing->value,
                'exchange_preference' => ProfileIntentExchangePreference::DiscussLater->value,
                'title' => 'Hidden financing need',
                'schedule_kind' => ProfileIntentScheduleKind::Ongoing->value,
                'timezone' => 'UTC',
                'round_trip' => false,
                'visibility' => ProfileItemVisibility::Private->value,
            ],
        );

        $viewer = Actor::factory()->create();

        Livewire::actingAs($viewer->user)
            ->test(Directory::class)
            ->assertSee('Residential electrician')
            ->assertDontSee('Hidden financing need')
            ->assertSee('Participant identity is not shared at this visibility level.');
    }

    public function test_quick_filters_distinguish_needs_offers_and_services(): void
    {
        $actor = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($actor->user);
        $create = app(CreateActorProfileIntent::class);

        $create->execute(
            $actor->user,
            $profile,
            ProfileIntentKind::Need,
            'Apartment',
            [
                'subject_kind' => ProfileIntentSubjectKind::Property->value,
                'arrangement_kind' => ProfileIntentArrangementKind::TemporaryUse->value,
                'exchange_preference' => ProfileIntentExchangePreference::CashOnly->value,
                'title' => 'Apartment to rent',
                'schedule_kind' => ProfileIntentScheduleKind::Ongoing->value,
                'timezone' => 'UTC',
                'round_trip' => false,
                'visibility' => ProfileItemVisibility::Authenticated->value,
            ],
        );

        $create->execute(
            $actor->user,
            $profile,
            ProfileIntentKind::Offer,
            'Masonry',
            [
                'subject_kind' => ProfileIntentSubjectKind::Service->value,
                'arrangement_kind' => ProfileIntentArrangementKind::Service->value,
                'exchange_preference' => ProfileIntentExchangePreference::OpenHybrid->value,
                'title' => 'Masonry service',
                'schedule_kind' => ProfileIntentScheduleKind::Ongoing->value,
                'timezone' => 'UTC',
                'round_trip' => false,
                'visibility' => ProfileItemVisibility::Authenticated->value,
            ],
        );

        $viewer = Actor::factory()->create();

        Livewire::actingAs($viewer->user)
            ->test(Directory::class)
            ->set('quick', 'needs')
            ->assertSee('Apartment to rent')
            ->assertDontSee('Masonry service')
            ->set('quick', 'services')
            ->assertSee('Masonry service')
            ->assertDontSee('Apartment to rent')
            ->set('quick', 'offers')
            ->assertSee('Masonry service');
    }

    public function test_authorized_record_beyond_two_hundred_hidden_records_remains_discoverable(): void
    {
        $owner = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($owner->user);

        app(CreateActorProfileIntent::class)->execute(
            $owner->user,
            $profile,
            ProfileIntentKind::Offer,
            'Visible service',
            [
                'subject_kind' => ProfileIntentSubjectKind::Service->value,
                'arrangement_kind' => ProfileIntentArrangementKind::Service->value,
                'exchange_preference' => ProfileIntentExchangePreference::DiscussLater->value,
                'title' => 'Authorized older result',
                'schedule_kind' => ProfileIntentScheduleKind::Ongoing->value,
                'timezone' => 'UTC',
                'round_trip' => false,
                'visibility' => ProfileItemVisibility::Authenticated->value,
            ],
        );

        ActorProfileIntent::factory()->count(205)->create([
            'visibility' => ProfileItemVisibility::Private,
        ]);

        $viewer = Actor::factory()->create();

        Livewire::actingAs($viewer->user)
            ->test(Directory::class)
            ->assertSee('Authorized older result');
    }

    public function test_directory_consumes_post_create_highlight_query_parameter(): void
    {
        $owner = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($owner->user);
        $intent = app(CreateActorProfileIntent::class)->execute(
            $owner->user,
            $profile,
            ProfileIntentKind::Need,
            'Highlighted need',
            [
                'title' => 'Highlighted record',
                'schedule_kind' => ProfileIntentScheduleKind::Ongoing->value,
                'timezone' => 'UTC',
                'round_trip' => false,
                'visibility' => ProfileItemVisibility::Authenticated->value,
            ],
        );

        Livewire::withQueryParams(['highlight' => $intent->uuid])
            ->actingAs($owner->user)
            ->test(Directory::class)
            ->assertSet('highlight', $intent->uuid)
            ->assertSee('Highlighted record')
            ->assertSee('ring-amber-300/60', false);
    }
}
