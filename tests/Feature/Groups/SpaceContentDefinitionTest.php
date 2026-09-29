<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Actions\Groups\ArchiveSpaceContent;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateGroupSpace;
use App\Actions\Groups\CreateSpaceContent;
use App\Actions\Groups\CreateSpaceContentDefinition;
use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Actions\Groups\ReviseSpaceContentDefinition;
use App\Actions\Groups\SetGroupSpaceParticipant;
use App\Actions\Groups\TransitionGroupMembership;
use App\Actions\Groups\UpdateSpaceContentDefinition;
use App\GroupRoleKey;
use App\Livewire\Groups\SpaceContentIndex;
use App\Livewire\Groups\SpaceContentShow;
use App\Livewire\Groups\SpaceManagement;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupInvitation;
use App\Models\GroupMembership;
use App\Models\GroupSpace;
use App\Models\SpaceContentDefinition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SpaceContentDefinitionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_definition_management_requires_space_management_authority(): void
    {
        [$group, $owner, $member] = $this->groupWithMember();
        $space = $this->createSpace($group, $owner, 'Engineering');
        $this->allow($space, $member, $owner);

        $this->expectException(AuthorizationException::class);

        app(CreateSpaceContentDefinition::class)->execute(
            $space,
            $member->user,
            'Daily Report',
            null,
            $this->dailyReportFields(),
        );
    }

    public function test_local_manager_can_define_content_only_in_their_space(): void
    {
        [$group, $owner, $manager] = $this->groupWithMember();
        $engineering = $this->createSpace($group, $owner, 'Engineering');
        $finance = $this->createSpace($group, $owner, 'Finance');

        app(SetGroupSpaceParticipant::class)->execute($engineering, $manager, $owner->user, 'allow', 'manager');

        $definition = app(CreateSpaceContentDefinition::class)->execute(
            $engineering,
            $manager->user,
            'Daily Report',
            null,
            $this->dailyReportFields(),
        );

        $this->assertSame($engineering->id, $definition->group_space_id);

        try {
            app(CreateSpaceContentDefinition::class)->execute(
                $finance,
                $manager->user,
                'Finance Note',
                null,
                $this->dailyReportFields(),
            );
            $this->fail('Local manager defined Content in another Space.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('space_content_definitions', [
                'group_space_id' => $finance->id,
                'slug' => 'finance-note',
            ]);
        }
    }

    public function test_schema_validation_rejects_duplicate_keys_unknown_types_and_malformed_select_options(): void
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Schema group', null);
        $space = $this->createSpace($group, $owner, 'Engineering');

        foreach ([
            [
                $this->field('same', 'One', 'short_text'),
                $this->field('same', 'Two', 'number'),
            ],
            [$this->field('unsafe', 'Unsafe', 'php_class')],
            [[
                ...$this->field('unsafe_attribute', 'Unsafe attribute', 'short_text'),
                'component' => 'App\\Dangerous\\ExecutableField',
            ]],
            [[
                'key' => 'status',
                'label' => 'Status',
                'type' => 'select',
                'required' => true,
                'help' => null,
                'options' => [['value' => 'bad value!', 'label' => 'Bad']],
            ]],
        ] as $fields) {
            try {
                app(CreateSpaceContentDefinition::class)->execute($space, $owner->user, 'Invalid '.fake()->unique()->word(), null, $fields);
                $this->fail('Invalid schema was accepted.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $this->assertDatabaseCount('space_content_definitions', 0);
    }

    public function test_payload_is_validated_against_exact_published_definition_version(): void
    {
        [$group, $owner, $member] = $this->groupWithMember();
        $space = $this->createSpace($group, $owner, 'Engineering');
        $this->allow($space, $member, $owner);
        $definition = $this->activeDailyReportDefinition($space, $owner);

        foreach ([
            ['work_completed' => 'Missing date.'],
            $this->validDailyReportPayload() + ['unexpected' => 'not allowed'],
            array_replace($this->validDailyReportPayload(), ['status' => 'unknown']),
        ] as $payload) {
            try {
                app(CreateSpaceContent::class)->execute($space, $definition, $member->user, 'Invalid payload', $payload);
                $this->fail('Invalid Content payload was accepted.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $this->assertDatabaseCount('space_contents', 0);
    }

    public function test_content_and_revision_preserve_actor_authorship_and_canonical_hashes(): void
    {
        [$group, $owner, $member] = $this->groupWithMember();
        $space = $this->createSpace($group, $owner, 'Engineering');
        $this->allow($space, $member, $owner);
        $definition = $this->activeDailyReportDefinition($space, $owner);

        $content = app(CreateSpaceContent::class)->execute(
            $space,
            $definition,
            $member->user,
            'Site report',
            $this->validDailyReportPayload(),
        );
        $revision = $content->currentRevisionRecord();
        $version = $definition->currentVersionRecord();

        $this->assertSame($member->id, $content->author_actor_id);
        $this->assertSame($member->id, $revision->created_by_actor_id);
        $this->assertSame(64, strlen($revision->content_hash));
        $this->assertSame(64, strlen($version->content_hash));
        $this->assertSame($version->id, $revision->definition_version_id);
    }

    public function test_content_and_definition_cannot_be_substituted_across_spaces(): void
    {
        [$group, $owner, $member] = $this->groupWithMember();
        $engineering = $this->createSpace($group, $owner, 'Engineering');
        $finance = $this->createSpace($group, $owner, 'Finance');
        $this->allow($engineering, $member, $owner);
        $this->allow($finance, $member, $owner);
        $engineeringDefinition = $this->activeDailyReportDefinition($engineering, $owner);

        try {
            app(CreateSpaceContent::class)->execute(
                $finance,
                $engineeringDefinition,
                $member->user,
                'Wrong Space',
                $this->validDailyReportPayload(),
            );
            $this->fail('Cross-Space definition substitution succeeded.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        $content = app(CreateSpaceContent::class)->execute(
            $engineering,
            $engineeringDefinition,
            $member->user,
            'Engineering report',
            $this->validDailyReportPayload(),
        );

        Livewire::actingAs($member->user)
            ->test(SpaceContentShow::class, [
                'group' => $group,
                'space' => $finance,
                'content' => $content,
            ])
            ->assertStatus(404);
    }

    public function test_restricted_space_access_controls_content_tab_and_direct_content_url(): void
    {
        [$group, $owner, $member] = $this->groupWithMember();
        $space = $this->createSpace($group, $owner, 'Engineering');
        $definition = $this->activeDailyReportDefinition($space, $owner);
        $this->allow($space, $owner, $owner);
        $content = app(CreateSpaceContent::class)->execute(
            $space,
            $definition,
            $owner->user,
            'Restricted report',
            $this->validDailyReportPayload(),
        );
        app(PublishSpaceContent::class)->execute($content, $owner->user);

        Livewire::actingAs($member->user)
            ->test(SpaceContentIndex::class, ['group' => $group, 'space' => $space])
            ->assertStatus(403);

        Livewire::actingAs($member->user)
            ->test(SpaceContentShow::class, ['group' => $group, 'space' => $space, 'content' => $content])
            ->assertStatus(403);
    }

    public function test_explicit_deny_immediately_blocks_mounted_content_component(): void
    {
        [$group, $owner, $member] = $this->groupWithMember();
        $space = $this->createSpace($group, $owner, 'Engineering');
        $this->allow($space, $member, $owner);
        $definition = $this->activeDailyReportDefinition($space, $owner);
        $content = app(CreateSpaceContent::class)->execute(
            $space,
            $definition,
            $member->user,
            'Mounted draft',
            $this->validDailyReportPayload(),
        );

        $component = Livewire::actingAs($member->user)
            ->test(SpaceContentShow::class, ['group' => $group, 'space' => $space, 'content' => $content]);

        app(SetGroupSpaceParticipant::class)->execute($space, $member, $owner->user, 'deny', 'participant');

        $component->call('publish')->assertStatus(403);
        $this->assertSame('draft', $content->fresh()->status);
    }

    public function test_revisions_increment_without_overwriting_historical_payload(): void
    {
        [$group, $owner, $member] = $this->groupWithMember();
        $space = $this->createSpace($group, $owner, 'Engineering');
        $this->allow($space, $member, $owner);
        $definition = $this->activeDailyReportDefinition($space, $owner);
        $content = app(CreateSpaceContent::class)->execute(
            $space,
            $definition,
            $member->user,
            'Revision one',
            $this->validDailyReportPayload(),
        );
        $first = $content->currentRevisionRecord();
        $firstHash = $first->content_hash;
        $firstPayload = $first->payload;

        $content = app(ReviseSpaceContent::class)->execute(
            $content,
            $member->user,
            'Revision two',
            array_replace($this->validDailyReportPayload(), ['workers_count' => 9]),
        );
        $content = app(ReviseSpaceContent::class)->execute(
            $content,
            $member->user,
            'Revision three',
            array_replace($this->validDailyReportPayload(), ['workers_count' => 12]),
        );

        $this->assertSame(3, $content->current_revision);
        $this->assertSame([1, 2, 3], $content->revisions()->orderBy('revision')->pluck('revision')->all());
        $this->assertSame($firstPayload, $first->fresh()->payload);
        $this->assertSame($firstHash, $first->fresh()->content_hash);
        $this->assertSame(12, $content->revisions()->where('revision', 3)->firstOrFail()->payload['workers_count']);
    }

    public function test_revising_active_definition_creates_new_draft_version_without_mutating_published_version(): void
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Definition revision group', null);
        $space = $this->createSpace($group, $owner, 'Engineering');
        $definition = $this->activeDailyReportDefinition($space, $owner);
        $published = $definition->currentVersionRecord();
        $publishedHash = $published->content_hash;
        $publishedSchema = $published->schema;

        $definition = app(ReviseSpaceContentDefinition::class)->execute($definition, $owner->user);

        $this->assertSame('draft', $definition->status);
        $this->assertSame(2, $definition->current_version);
        $this->assertSame(2, $definition->versions()->count());
        $this->assertNotNull($published->fresh()->published_at);
        $this->assertSame($publishedHash, $published->fresh()->content_hash);
        $this->assertSame($publishedSchema, $published->fresh()->schema);

        $draftVersion = $definition->currentVersionRecord();
        $this->assertNull($draftVersion->published_at);
        $this->assertSame($publishedSchema, $draftVersion->schema);

        app(UpdateSpaceContentDefinition::class)->execute(
            $definition,
            $owner->user,
            'Daily Report',
            'Version two.',
            [...$this->dailyReportFields(), $this->field('weather', 'Weather', 'short_text')],
        );
        app(ActivateSpaceContentDefinition::class)->execute($definition->refresh(), $owner->user);

        $this->assertSame($publishedHash, $published->fresh()->content_hash);
        $this->assertCount(5, $published->fresh()->schema['fields']);
        $this->assertCount(6, $definition->fresh()->currentVersionRecord()->schema['fields']);
    }

    public function test_published_definition_version_is_immutable(): void
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Immutable definition', null);
        $space = $this->createSpace($group, $owner, 'Engineering');
        $definition = $this->activeDailyReportDefinition($space, $owner);
        $version = $definition->currentVersionRecord();

        $this->expectException(LogicException::class);

        $version->update(['schema' => ['fields' => [$this->field('changed', 'Changed', 'short_text')]]]);
    }

    public function test_another_explicitly_allowed_participant_can_read_published_content(): void
    {
        [$group, $owner, $author] = $this->groupWithMember();
        [$reader] = $this->addMember($group, $owner);
        $space = $this->createSpace($group, $owner, 'Engineering');
        $this->allow($space, $author, $owner);
        $this->allow($space, $reader, $owner);
        $definition = $this->activeDailyReportDefinition($space, $owner);
        $content = app(CreateSpaceContent::class)->execute(
            $space,
            $definition,
            $author->user,
            'Shared published report',
            $this->validDailyReportPayload(),
        );
        app(PublishSpaceContent::class)->execute($content, $author->user);

        Livewire::actingAs($reader->user)
            ->test(SpaceContentIndex::class, ['group' => $group, 'space' => $space])
            ->assertSee('Shared published report');
    }

    public function test_group_wide_space_manager_cannot_read_restricted_content_without_explicit_allow(): void
    {
        [$group, $owner, $member] = $this->groupWithMember();
        $space = $this->createSpace($group, $owner, 'Engineering');
        $this->allow($space, $member, $owner);
        $definition = $this->activeDailyReportDefinition($space, $owner);
        $content = app(CreateSpaceContent::class)->execute(
            $space,
            $definition,
            $member->user,
            'Manager must not read this',
            $this->validDailyReportPayload(),
        );
        app(PublishSpaceContent::class)->execute($content, $member->user);

        $this->assertTrue($owner->user->can('manage', $space));
        $this->assertFalse($owner->user->can('view', $space));

        Livewire::actingAs($owner->user)
            ->test(SpaceContentIndex::class, ['group' => $group, 'space' => $space])
            ->assertStatus(403);
    }

    public function test_archived_content_is_not_in_normal_content_listing(): void
    {
        [$group, $owner, $member] = $this->groupWithMember();
        $space = $this->createSpace($group, $owner, 'Engineering');
        $this->allow($space, $member, $owner);
        $definition = $this->activeDailyReportDefinition($space, $owner);
        $content = app(CreateSpaceContent::class)->execute(
            $space,
            $definition,
            $member->user,
            'Archived report title',
            $this->validDailyReportPayload(),
        );
        $content = app(PublishSpaceContent::class)->execute($content, $member->user);
        app(ArchiveSpaceContent::class)->execute($content, $member->user);

        Livewire::actingAs($member->user)
            ->test(SpaceContentIndex::class, ['group' => $group, 'space' => $space])
            ->assertDontSee('Archived report title');
    }

    public function test_explicitly_allowed_non_member_actor_gets_content_only_inside_allowed_space(): void
    {
        $owner = Actor::factory()->create();
        $outsider = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'External Content collaboration', null);
        $engineering = $this->createSpace($group, $owner, 'Engineering');
        $finance = $this->createSpace($group, $owner, 'Finance');
        $this->allow($engineering, $outsider, $owner);
        $definition = $this->activeDailyReportDefinition($engineering, $owner);

        $content = app(CreateSpaceContent::class)->execute(
            $engineering,
            $definition,
            $outsider->user,
            'External report',
            $this->validDailyReportPayload(),
        );
        app(PublishSpaceContent::class)->execute($content, $outsider->user);

        Livewire::actingAs($outsider->user)
            ->test(SpaceContentIndex::class, ['group' => $group, 'space' => $engineering])
            ->assertSee('External report');

        Livewire::actingAs($outsider->user)
            ->test(SpaceContentIndex::class, ['group' => $group, 'space' => $finance])
            ->assertStatus(403);

        $this->assertDatabaseMissing('group_memberships', [
            'group_id' => $group->id,
            'actor_id' => $outsider->id,
        ]);
    }

    public function test_daily_report_rendered_journey_uses_definition_builder_and_content_form(): void
    {
        [$group, $owner, $member] = $this->groupWithMember();

        $management = Livewire::actingAs($owner->user)
            ->test(SpaceManagement::class, ['group' => $group])
            ->set('newSpaceName', 'Engineering')
            ->set('newSpaceAccessMode', 'restricted')
            ->call('createSpace')
            ->assertHasNoErrors();

        $space = $group->spaces()->where('slug', 'engineering')->firstOrFail();
        $management
            ->set('participantActorId', (string) $member->id)
            ->set('participantAccess', 'allow')
            ->set('participantRole', 'participant')
            ->call('setParticipant')
            ->set('definitionName', 'Daily Report')
            ->set('definitionDescription', 'Daily engineering progress report.')
            ->set('definitionFields', $this->builderDailyReportFields())
            ->call('saveDefinition')
            ->assertHasNoErrors();

        $definition = $space->contentDefinitions()->where('slug', 'daily-report')->firstOrFail();
        $management->call('activateDefinition', $definition->id)->assertHasNoErrors();

        $component = Livewire::actingAs($member->user)
            ->test(SpaceContentIndex::class, ['group' => $group, 'space' => $space])
            ->assertSee('Content')
            ->set('definitionId', (string) $definition->id)
            ->set('title', 'Daily Report — 2026-09-13')
            ->set('payload', $this->validDailyReportPayload())
            ->call('create')
            ->assertHasNoErrors();

        $content = $space->contents()->sole();
        app(PublishSpaceContent::class)->execute($content, $member->user);

        $this->assertSame('published', $content->fresh()->status);
        $this->assertSame($member->id, $content->author_actor_id);
    }

    public function test_invitation_page_exposes_no_space_content(): void
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Invitation isolation', null);
        $space = $this->createSpace($group, $owner, 'Engineering');
        $this->allow($space, $owner, $owner);
        $definition = $this->activeDailyReportDefinition($space, $owner);
        $content = app(CreateSpaceContent::class)->execute(
            $space,
            $definition,
            $owner->user,
            'PRIVATE SPACE CONTENT TITLE',
            $this->validDailyReportPayload(),
        );
        app(PublishSpaceContent::class)->execute($content, $owner->user);

        $invitation = GroupInvitation::factory()->create([
            'group_id' => $group->id,
            'invited_by_actor_id' => $owner->id,
            'content' => 'Existing invitation preview remains unchanged.',
        ]);
        $token = $invitation->plainTextToken();
        $this->assertNotNull($token);

        $this->get(route('invitations.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Existing invitation preview remains unchanged.')
            ->assertDontSee('PRIVATE SPACE CONTENT TITLE');
    }

    /** @return array{Group, Actor, Actor, GroupMembership} */
    private function groupWithMember(): array
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Content group', null);
        [$member, $membership] = $this->addMember($group, $owner);

        return [$group, $owner, $member, $membership];
    }

    /** @return array{Actor, GroupMembership} */
    private function addMember(Group $group, Actor $owner): array
    {
        $member = Actor::factory()->create();
        $membership = $group->memberships()->create([
            'actor_id' => $member->id,
            'status' => 'active',
        ]);
        $roles = app(GroupRoleProvisioner::class);
        $roles->grant($member, $group, $roles->builtInRole($group, GroupRoleKey::Member));
        app(TransitionGroupMembership::class)->recordInitial($membership, $owner, 'Content test member added.');

        return [$member, $membership];
    }

    private function createSpace(Group $group, Actor $owner, string $name): GroupSpace
    {
        return app(CreateGroupSpace::class)->execute($group, $owner->user, $name, 'restricted');
    }

    private function allow(GroupSpace $space, Actor $actor, Actor $grantor): void
    {
        app(SetGroupSpaceParticipant::class)->execute($space, $actor, $grantor->user, 'allow', 'participant');
    }

    private function activeDailyReportDefinition(GroupSpace $space, Actor $manager): SpaceContentDefinition
    {
        $definition = app(CreateSpaceContentDefinition::class)->execute(
            $space,
            $manager->user,
            'Daily Report',
            'Engineering daily report.',
            $this->dailyReportFields(),
        );

        return app(ActivateSpaceContentDefinition::class)->execute($definition, $manager->user);
    }

    /** @return array<int, array<string, mixed>> */
    private function dailyReportFields(): array
    {
        return [
            $this->field('report_date', 'Report date', 'date', true),
            $this->field('work_completed', 'Work completed', 'long_text', true),
            $this->field('workers_count', 'Workers count', 'number'),
            $this->field('safety_issue', 'Safety issue', 'boolean'),
            [
                'key' => 'status',
                'label' => 'Status',
                'type' => 'select',
                'required' => true,
                'help' => null,
                'options' => [
                    ['value' => 'on_track', 'label' => 'On track'],
                    ['value' => 'delayed', 'label' => 'Delayed'],
                    ['value' => 'blocked', 'label' => 'Blocked'],
                ],
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function builderDailyReportFields(): array
    {
        return [
            ['key' => 'report_date', 'label' => 'Report date', 'type' => 'date', 'required' => true, 'help' => '', 'options_text' => ''],
            ['key' => 'work_completed', 'label' => 'Work completed', 'type' => 'long_text', 'required' => true, 'help' => '', 'options_text' => ''],
            ['key' => 'workers_count', 'label' => 'Workers count', 'type' => 'number', 'required' => false, 'help' => '', 'options_text' => ''],
            ['key' => 'safety_issue', 'label' => 'Safety issue', 'type' => 'boolean', 'required' => false, 'help' => '', 'options_text' => ''],
            ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'help' => '', 'options_text' => "on_track|On track\ndelayed|Delayed\nblocked|Blocked"],
        ];
    }

    /** @return array<string, mixed> */
    private function validDailyReportPayload(): array
    {
        return [
            'report_date' => '2026-09-13',
            'work_completed' => 'Installed structural framing on level two.',
            'workers_count' => 4,
            'safety_issue' => false,
            'status' => 'on_track',
        ];
    }

    /** @return array<string, mixed> */
    private function field(string $key, string $label, string $type, bool $required = false): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'required' => $required,
            'help' => null,
            'options' => [],
        ];
    }
}
