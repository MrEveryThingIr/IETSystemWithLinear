<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\RevokeGroupInvitation;
use App\GroupRoleKey;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupInvitation;
use App\Models\GroupMembership;
use App\Models\User;
use App\PlatformCapability;
use App\Support\NotificationOutboxWriter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
class Invitations extends Component
{
    use WithPagination;

    public Group $group;

    /** Join link settings */
    public string $linkLabel = '';

    public string $linkRoleId = '';

    public ?int $linkMaxUses = null;

    public int $linkDays = 30;

    public bool $linkDirect = true;

    public bool $linkAllowsRegistration = false;

    /** Invite someone who already has an account */
    public string $search = '';

    public string $personRoleId = '';

    public ?string $createdInvitationUrl = null;

    public ?string $invitedUsername = null;

    public function mount(Group $group): void
    {
        Gate::authorize('createInvitation', $group);
        $this->group = $group;
    }

    public function createJoinLink(): void
    {
        Gate::authorize('createInvitation', $this->group);
        $data = $this->validate([
            'linkLabel' => ['nullable', 'string', 'max:120'],
            'linkRoleId' => ['nullable', 'string'],
            'linkMaxUses' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'linkDays' => ['required', 'integer', 'min:1', 'max:365'],
            'linkDirect' => ['boolean'],
            'linkAllowsRegistration' => ['boolean'],
        ]);
        abort_if($data['linkAllowsRegistration'] && ! $this->canOpenRegistration(), 403);

        $token = GroupInvitation::issueToken();
        GroupInvitation::create([
            'group_id' => $this->group->id,
            'invited_by_actor_id' => $this->actor()->id,
            'email' => null,
            'join_mode' => $data['linkDirect'] ? GroupInvitation::JOIN_DIRECT : GroupInvitation::JOIN_REVIEW,
            'granted_role_id' => $this->grantableRoleId($data['linkRoleId']),
            'label' => trim((string) $data['linkLabel']) ?: null,
            'allows_registration' => (bool) $data['linkAllowsRegistration'],
            'token' => $token,
            'expires_at' => now()->addDays($data['linkDays']),
            'max_uses' => $data['linkMaxUses'],
            'uses_count' => 0,
        ]);

        $this->createdInvitationUrl = route('invitations.show', $token);
        $this->invitedUsername = null;
        $this->reset('linkLabel', 'linkMaxUses');
        $this->resetPage();
        session()->flash('status', __('classroom.invite.link_created'));
    }

    public function invitePerson(int $userId, NotificationOutboxWriter $notifications): void
    {
        Gate::authorize('createInvitation', $this->group);
        $target = $this->candidates($this->search)->firstWhere('id', $userId);
        abort_unless($target instanceof User, 422, __('classroom.invite.person_unavailable'));

        $token = GroupInvitation::issueToken();
        $invitation = GroupInvitation::create([
            'group_id' => $this->group->id,
            'invited_by_actor_id' => $this->actor()->id,
            'email' => $target->email,
            'join_mode' => GroupInvitation::JOIN_DIRECT,
            'granted_role_id' => $this->grantableRoleId($this->personRoleId),
            'label' => '@'.$target->username,
            'token' => $token,
            'expires_at' => now()->addDays(14),
            'max_uses' => 1,
            'uses_count' => 0,
        ]);

        $url = route('invitations.show', $token);
        $notifications->request(
            $target,
            "group-invitation:{$invitation->id}:{$target->id}",
            'group.invitation',
            [
                'title_key' => 'classroom.invite.notification_title',
                'title_params' => ['group' => $this->group->name],
                'body_key' => 'classroom.invite.notification_body',
                'body_params' => ['inviter' => (string) auth()->user()?->username],
                'url' => $url,
            ],
            null,
            'group_invitation',
            (string) $invitation->id,
        );

        $this->createdInvitationUrl = $url;
        $this->invitedUsername = $target->username;
        $this->search = '';
        $this->resetPage();
        session()->flash('status', __('classroom.invite.person_invited', ['username' => $target->username]));
    }

    public function revoke(int $id, RevokeGroupInvitation $revoker): void
    {
        Gate::authorize('createInvitation', $this->group);
        /** @var GroupInvitation $invitation */
        $invitation = GroupInvitation::query()->where('group_id', $this->group->id)->findOrFail($id);
        $revoker->execute($invitation);
    }

    public function render(): View
    {
        Gate::authorize('createInvitation', $this->group);

        return view('livewire.groups.invitations', [
            'roles' => $this->grantableRoles(),
            'canOpenRegistration' => $this->canOpenRegistration(),
            'canGrantRoles' => Gate::allows('manageRoles', $this->group),
            'candidates' => $this->candidates($this->search),
            'invitations' => GroupInvitation::query()
                ->with(['admissions.candidate.user', 'grantedRole'])
                ->where('group_id', $this->group->id)
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    /**
     * Active, verified people who are not current members, matched by the
     * beginning of their username or by their profile display name.
     *
     * @return Collection<int, User>
     */
    private function candidates(string $search): Collection
    {
        $search = trim(ltrim($search, '@'));
        if (mb_strlen($search) < 2) {
            return collect();
        }

        $memberActorIds = GroupMembership::query()
            ->where('group_id', $this->group->id)
            ->whereIn('status', ['active', 'suspended'])
            ->pluck('actor_id');

        // Portable LIKE escaping (SQLite has no default escape character).
        $like = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);

        return User::query()
            ->with('actor.profile')
            ->where('status', 'active')
            ->whereNotNull('email_verified_at')
            ->whereKeyNot((int) auth()->id())
            ->whereHas('actor', fn ($query) => $query->whereNotIn('id', $memberActorIds))
            ->where(function ($query) use ($like): void {
                $query->whereRaw("username LIKE ? ESCAPE '!'", [$like.'%'])
                    ->orWhereHas('actor.profile', fn ($profile) => $profile->whereRaw("display_name LIKE ? ESCAPE '!'", ['%'.$like.'%']));
            })
            ->orderBy('username')
            ->limit(8)
            ->get();
    }

    /** @return Collection<int, Role> */
    private function grantableRoles(): Collection
    {
        return app(GroupRoleProvisioner::class)->roles($this->group)
            ->reject(fn (Role $role): bool => in_array($role->getAttribute('system_key'), [GroupRoleKey::Owner->value, GroupRoleKey::Member->value], true))
            ->values();
    }

    private function grantableRoleId(string $roleId): ?int
    {
        if ($roleId === '' || ! ctype_digit($roleId)) {
            return null;
        }

        abort_unless(Gate::allows('manageRoles', $this->group), 403, __('classroom.invite.role_requires_permission'));
        $role = $this->grantableRoles()->firstWhere('id', (int) $roleId);
        abort_unless($role instanceof Role, 422);

        return (int) $role->id;
    }

    private function canOpenRegistration(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasPlatformCapability(PlatformCapability::ManageUsers);
    }

    private function actor(): Actor
    {
        /** @var Actor $actor */
        $actor = Actor::query()->where('user_id', auth()->id())->firstOrFail();

        return $actor;
    }
}
