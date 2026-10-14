<?php

namespace Tests\Feature\Classroom;

use App\Actions\Content\ToggleAnnotationEndorsement;
use App\Actions\Content\UpdateContentRoleRules;
use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Actions\Groups\AddSpaceContentAnnotation;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateSpaceContent;
use App\Actions\Groups\CreateSpaceContentDefinition;
use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\PostGroupSpaceMessage;
use App\Actions\Groups\PublishSpaceContent;
use App\GroupPermission;
use App\GroupRoleKey;
use App\Livewire\Auth\Register;
use App\Livewire\Groups\Invitations;
use App\Livewire\Groups\Show as GroupShow;
use App\Livewire\Groups\SpaceChat;
use App\Models\Actor;
use App\Models\ConversationMessage;
use App\Models\Group;
use App\Models\GroupInvitation;
use App\Models\GroupMembership;
use App\Models\GroupSpace;
use App\Models\NotificationOutbox;
use App\Models\SpaceContent;
use App\Models\SpaceContentAnnotation;
use App\Models\SpaceContentRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ClassroomGroupExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_join_link_admits_an_existing_account_immediately_with_its_role(): void
    {
        [$group, $owner] = $this->group();
        $teacherRole = $this->teacherRole($group);

        $component = Livewire::actingAs($owner->user)
            ->test(Invitations::class, ['group' => $group])
            ->set('linkLabel', 'English class')
            ->set('linkRoleId', (string) $teacherRole->id)
            ->set('linkDirect', true)
            ->call('createJoinLink')
            ->assertHasNoErrors();

        $url = (string) $component->get('createdInvitationUrl');
        $token = basename($url);
        $invitation = GroupInvitation::query()->where('group_id', $group->id)->sole();
        $this->assertSame(GroupInvitation::JOIN_DIRECT, $invitation->join_mode);
        $this->assertNull($invitation->email);
        $this->assertFalse($invitation->allows_registration);

        $teacher = Actor::factory()->create();
        $this->actingAs($teacher->user)
            ->post(route('invitations.accept', $token))
            ->assertRedirect(route('groups.show', $group));

        $this->assertTrue(GroupMembership::query()
            ->where('group_id', $group->id)
            ->where('actor_id', $teacher->id)
            ->where('status', 'active')
            ->exists());
        $this->assertContains($teacherRole->id, app(GroupRoleProvisioner::class)->roleIds($teacher, $group));

        $this->actingAs($teacher->user)->get(route('groups.show', $group))->assertOk();
    }

    public function test_review_links_keep_the_manual_admission_flow(): void
    {
        [$group, $owner] = $this->group();

        $component = Livewire::actingAs($owner->user)
            ->test(Invitations::class, ['group' => $group])
            ->set('linkDirect', false)
            ->call('createJoinLink');
        $token = basename((string) $component->get('createdInvitationUrl'));

        $candidate = Actor::factory()->create();
        $response = $this->actingAs($candidate->user)->post(route('invitations.accept', $token));

        $response->assertRedirectContains('/admissions/');
        $this->assertFalse(GroupMembership::query()->where('group_id', $group->id)->where('actor_id', $candidate->id)->exists());
    }

    public function test_only_explicitly_opened_links_allow_creating_a_new_account(): void
    {
        [$group, $owner] = $this->group();

        $closed = Livewire::actingAs($owner->user)
            ->test(Invitations::class, ['group' => $group])
            ->call('createJoinLink');
        $closedToken = basename((string) $closed->get('createdInvitationUrl'));

        auth()->logout();
        $this->get(route('invitations.register', $closedToken))->assertNotFound();

        // Ordinary group managers cannot open registration.
        Livewire::actingAs($owner->user)
            ->test(Invitations::class, ['group' => $group])
            ->set('linkAllowsRegistration', true)
            ->call('createJoinLink')
            ->assertForbidden();

        $openToken = GroupInvitation::issueToken();
        GroupInvitation::create([
            'group_id' => $group->id,
            'invited_by_actor_id' => $owner->id,
            'join_mode' => GroupInvitation::JOIN_DIRECT,
            'allows_registration' => true,
            'token' => $openToken,
            'expires_at' => now()->addDay(),
            'max_uses' => null,
            'uses_count' => 0,
        ]);

        auth()->logout();
        Livewire::test(Register::class, ['token' => $openToken])->assertOk();
        $this->get(route('invitations.show', $openToken))
            ->assertOk()
            ->assertSee(route('invitations.register', $openToken), false);
    }

    public function test_people_with_accounts_can_be_found_by_name_and_invited_without_knowing_their_email(): void
    {
        [$group, $owner] = $this->group();
        $friend = Actor::factory()->create();
        $friend->user->forceFill(['username' => 'sara_student'])->save();

        Livewire::actingAs($owner->user)
            ->test(Invitations::class, ['group' => $group])
            ->set('search', 'sara')
            ->assertSee('sara_student')
            ->call('invitePerson', $friend->user->id)
            ->assertHasNoErrors();

        $invitation = GroupInvitation::query()->where('group_id', $group->id)->sole();
        $this->assertSame($friend->user->email, $invitation->email);
        $this->assertSame(1, $invitation->max_uses);
        $this->assertTrue($invitation->admitsDirectly());

        $notification = NotificationOutbox::query()->where('recipient_user_id', $friend->user->id)->sole();
        $this->assertSame('group.invitation', $notification->kind);

        // Members are not offered again.
        Livewire::actingAs($owner->user)
            ->test(Invitations::class, ['group' => $group])
            ->set('search', $owner->user->username)
            ->assertDontSee('wire:click="invitePerson('.$owner->user->id.')"', false);
    }

    public function test_chat_authors_can_edit_and_delete_their_own_messages_without_losing_history(): void
    {
        [$group, $owner, $space] = $this->group();
        $friend = $this->joinDirectly($group, $owner);

        $message = app(PostGroupSpaceMessage::class)->execute($space, $friend->user, 'Hello class');

        Livewire::actingAs($owner->user)
            ->test(SpaceChat::class, ['group' => $group, 'space' => $space])
            ->call('startEditing', $message->id)
            ->assertForbidden();

        Livewire::actingAs($friend->user)
            ->test(SpaceChat::class, ['group' => $group, 'space' => $space])
            ->call('startEditing', $message->id)
            ->set('editingBody', 'Hello everyone')
            ->call('saveEdit')
            ->assertHasNoErrors()
            ->assertSee('Hello everyone')
            ->call('deleteMessage', $message->id)
            ->assertSee(__('classroom.chat.deleted'))
            ->assertDontSee('Hello everyone');

        $this->assertSame('Hello class', ConversationMessage::query()->findOrFail($message->id)->body);
        $this->assertSame(2, $message->revisions()->count());
    }

    public function test_group_home_shows_chat_content_members_and_pinned_guide(): void
    {
        [$group, $owner, $space] = $this->group();
        $content = $this->publishedContent($space, $owner, 'How to use this class space');

        Livewire::actingAs($owner->user)
            ->test(GroupShow::class, ['group' => $group])
            ->assertSee('How to use this class space')
            ->assertSee(__('classroom.group.invite'))
            ->call('togglePin', $content->id)
            ->assertSee(__('classroom.group.start_here'));

        $this->assertNotNull($content->refresh()->pinned_at);

        $member = $this->joinDirectly($group, $owner);
        Livewire::actingAs($member->user)
            ->test(GroupShow::class, ['group' => $group])
            ->assertSee(__('classroom.group.start_here'))
            ->assertDontSee(__('classroom.group.manage'))
            ->call('togglePin', $content->id)
            ->assertForbidden();
    }

    public function test_content_author_decides_what_each_role_may_do_and_teachers_endorse_answers(): void
    {
        [$group, $owner, $space] = $this->group();
        $teacherRole = $this->teacherRole($group);
        $memberRole = app(GroupRoleProvisioner::class)->builtInRole($group, GroupRoleKey::Member);
        $student = $this->joinDirectly($group, $owner);
        $teacher = $this->joinDirectly($group, $owner, $teacherRole);
        $content = $this->publishedContent($space, $owner, 'File 1A');
        $revision = $content->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $revision);

        app(UpdateContentRoleRules::class)->execute($content, $owner->user, [
            $memberRole->id => ['kinds' => ['question', 'answer', 'reply'], 'endorse' => false],
            $teacherRole->id => ['kinds' => ['answer', 'reply', 'note'], 'endorse' => true],
        ]);
        $content->refresh();

        $question = app(AddSpaceContentAnnotation::class)->execute(
            $content, $revision, $student->user, 'What does "maiden name" mean?', null, SpaceContentAnnotation::KIND_QUESTION,
        );
        $answer = app(AddSpaceContentAnnotation::class)->execute(
            $content, $revision, $student->user, 'The surname before marriage.', $question, SpaceContentAnnotation::KIND_ANSWER,
        );

        try {
            app(AddSpaceContentAnnotation::class)->execute(
                $content, $revision, $student->user, 'A private study note', null, SpaceContentAnnotation::KIND_NOTE,
            );
            $this->fail('Members may not add notes on this content.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        try {
            app(ToggleAnnotationEndorsement::class)->execute($answer, $student->user);
            $this->fail('Members may not endorse answers here.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertTrue(app(ToggleAnnotationEndorsement::class)->execute($answer, $teacher->user));
        $this->assertSame(1, $answer->endorsements()->count());
        $this->assertFalse(app(ToggleAnnotationEndorsement::class)->execute($answer, $teacher->user));
        $this->assertSame(0, $answer->endorsements()->count());

        // Rules for roles of another group are ignored.
        $otherGroup = app(CreateGroup::class)->execute($owner, 'Other class', null);
        $foreignRole = app(GroupRoleProvisioner::class)->createRole($otherGroup, 'Visitor', [GroupPermission::Participate->value]);
        $updated = app(UpdateContentRoleRules::class)->execute($content, $owner->user, [
            $foreignRole->id => ['kinds' => ['note'], 'endorse' => true],
        ]);
        $this->assertArrayNotHasKey('role_rules', $updated->interaction_settings);
    }

    /** @return array{0: Group, 1: Actor, 2: GroupSpace} */
    private function group(): array
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'English File class', 'Intermediate Plus');
        $space = GroupSpace::query()->where('group_id', $group->id)->where('is_default', true)->sole();

        return [$group, $owner, $space];
    }

    private function teacherRole(Group $group): Role
    {
        return app(GroupRoleProvisioner::class)->createRole($group, 'Teacher', [GroupPermission::Participate->value]);
    }

    private function joinDirectly(Group $group, Actor $owner, ?Role $role = null): Actor
    {
        $token = GroupInvitation::issueToken();
        GroupInvitation::create([
            'group_id' => $group->id,
            'invited_by_actor_id' => $owner->id,
            'join_mode' => GroupInvitation::JOIN_DIRECT,
            'granted_role_id' => $role?->id,
            'token' => $token,
            'expires_at' => now()->addDay(),
            'max_uses' => null,
            'uses_count' => 0,
        ]);

        $actor = Actor::factory()->create();
        $this->actingAs($actor->user)
            ->post(route('invitations.accept', $token))
            ->assertRedirect(route('groups.show', $group));

        return $actor;
    }

    private function publishedContent(GroupSpace $space, Actor $owner, string $title): SpaceContent
    {
        $definition = app(CreateSpaceContentDefinition::class)->execute(
            $space,
            $owner->user,
            'Lesson '.uniqid(),
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
        $content = app(CreateSpaceContent::class)->execute($space, $definition, $owner->user, $title, ['body' => 'Names and nicknames.']);

        return app(PublishSpaceContent::class)->execute($content, $owner->user);
    }
}
