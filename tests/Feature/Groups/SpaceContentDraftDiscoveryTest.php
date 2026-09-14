<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateGroupSpace;
use App\Actions\Groups\CreateSpaceContent;
use App\Actions\Groups\CreateSpaceContentDefinition;
use App\Actions\Groups\SetGroupSpaceParticipant;
use App\Livewire\Groups\SpaceContentIndex;
use App\Models\Actor;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SpaceContentDraftDiscoveryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_draft_discovery_distinguishes_author_manager_and_ordinary_participant(): void
    {
        $owner = Actor::factory()->create();
        $author = Actor::factory()->create();
        $participant = Actor::factory()->create();

        $group = app(CreateGroup::class)->execute($owner, 'Draft discovery group', null);
        $space = app(CreateGroupSpace::class)->execute($group, $owner->user, 'Editorial', 'restricted');

        app(SetGroupSpaceParticipant::class)->execute($space, $owner, $owner->user, 'allow', 'manager');
        app(SetGroupSpaceParticipant::class)->execute($space, $author, $owner->user, 'allow', 'participant');
        app(SetGroupSpaceParticipant::class)->execute($space, $participant, $owner->user, 'allow', 'participant');

        $definition = app(CreateSpaceContentDefinition::class)->execute(
            $space,
            $owner->user,
            'Article',
            null,
            [[
                'key' => 'body',
                'label' => 'Body',
                'type' => 'long_text',
                'required' => true,
                'help' => null,
                'options' => [],
            ]],
        );
        $definition = app(ActivateSpaceContentDefinition::class)->execute($definition, $owner->user);

        app(CreateSpaceContent::class)->execute(
            $space,
            $definition,
            $author->user,
            'Private editorial draft',
            ['body' => 'Still private'],
        );

        Livewire::actingAs($author->user)
            ->test(SpaceContentIndex::class, compact('group', 'space'))
            ->assertSee('Private editorial draft')
            ->assertDontSee('Space drafts');

        Livewire::actingAs($participant->user)
            ->test(SpaceContentIndex::class, compact('group', 'space'))
            ->assertDontSee('Private editorial draft')
            ->assertDontSee('Space drafts');

        Livewire::actingAs($owner->user)
            ->test(SpaceContentIndex::class, compact('group', 'space'))
            ->assertSee('Space drafts')
            ->assertSee('Private editorial draft')
            ->assertSee($author->user->username);
    }
}
