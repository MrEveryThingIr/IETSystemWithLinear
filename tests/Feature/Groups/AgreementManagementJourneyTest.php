<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\ManageGroupAgreement;
use App\Livewire\Groups\Agreements;
use App\Livewire\Groups\Show as GroupShow;
use App\Models\Actor;
use App\Models\GroupAgreement;
use App\Models\GroupAgreementVersion;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AgreementManagementJourneyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_discover_agreements_and_publish_an_approved_version_immediately(): void
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Learning group', null);

        Livewire::actingAs($owner->user)
            ->test(GroupShow::class, ['group' => $group])
            ->assertSee(route('groups.agreements', $group), false)
            ->assertSee('Agreements');

        $component = Livewire::actingAs($owner->user)
            ->test(Agreements::class, ['group' => $group])
            ->set('name', 'Community agreement')
            ->set('content', 'Respect other members and protect private discussions.')
            ->set('required', true)
            ->call('create')
            ->assertHasNoErrors()
            ->assertSee('Respect other members and protect private discussions.');

        $agreement = GroupAgreement::query()->sole();
        $version = GroupAgreementVersion::query()->sole();

        $component
            ->call('propose', $version->id)
            ->call('approve', $version->id)
            ->call('activate', $version->id)
            ->assertHasNoErrors()
            ->assertSee('Active');

        $this->assertSame('active', $version->refresh()->status);
        $this->assertNotNull($version->activated_at);
        $this->assertTrue($agreement->required_for_admission);
    }

    public function test_creating_a_revision_preserves_the_active_version_until_the_new_version_is_activated(): void
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Versioned group', null);
        $manager = app(ManageGroupAgreement::class);
        $agreement = $manager->create($group, $owner, 'Rules', true, 'Version one');
        $first = $agreement->versions()->sole();
        $manager->propose($first, $owner);
        $manager->approve($first, $owner);
        $manager->activate($first, $owner);

        Livewire::actingAs($owner->user)
            ->test(Agreements::class, ['group' => $group])
            ->call('startRevision', $agreement->id)
            ->set('revisionContent', 'Version two')
            ->set('revisionRationale', 'Clarify member responsibilities.')
            ->set('reacceptanceRequired', true)
            ->call('revise', $agreement->id)
            ->assertHasNoErrors()
            ->assertSee('Version two')
            ->assertSee('Version one');

        $second = $agreement->versions()->where('version', 2)->sole();

        $this->assertSame('active', $first->refresh()->status);
        $this->assertSame('draft', $second->status);

        $manager->propose($second, $owner);
        $manager->approve($second, $owner);
        $manager->activate($second, $owner);

        $this->assertSame('superseded', $first->refresh()->status);
        $this->assertSame($second->id, $first->superseded_by_version_id);
        $this->assertSame('active', $second->refresh()->status);
    }

    public function test_scheduling_interprets_local_group_time_and_stores_utc(): void
    {
        Carbon::setTestNow('2026-01-01 00:00:00 UTC');
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Toronto group', null);
        $group->update(['timezone' => 'America/Toronto']);
        $manager = app(ManageGroupAgreement::class);
        $agreement = $manager->create($group, $owner, 'Rules', true, 'Version one');
        $version = $agreement->versions()->sole();
        $manager->propose($version, $owner);
        $manager->approve($version, $owner);

        Livewire::actingAs($owner->user)
            ->test(Agreements::class, ['group' => $group])
            ->set('effectiveFrom', '2026-01-01T09:00')
            ->call('schedule', $version->id)
            ->assertHasNoErrors();

        $this->assertSame('2026-01-01 14:00:00', $version->refresh()->effective_from->utc()->format('Y-m-d H:i:s'));
    }
}
