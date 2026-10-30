<?php

namespace App\Livewire\Content;

use App\Actions\Content\CreateContentInvitation;
use App\Actions\Content\RevokeContentInvitation;
use App\Actions\Content\SetContentPublicAccess;
use App\ContextKind;
use App\Models\ContentInvitation;
use App\Models\SpaceContent;
use App\Models\User;
use App\Support\TemporalPreferences;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "Share outside": make published Content readable by anyone, and create
 * personal invitation links. Embedded in the reader and the Studio; every
 * change goes through the Content actions and their authorization.
 */
class SharePanel extends Component
{
    #[Locked]
    public int $contentId;

    public string $inviteeName = '';

    public string $inviteMessage = '';

    public int $expiresInDays = 0;

    public ?string $status = null;

    #[Locked]
    public ?string $createdInvitationUuid = null;

    public function mount(SpaceContent $content): void
    {
        $this->contentId = $content->id;
        abort_unless(Gate::forUser($this->user())->allows('view', $content), 403);
    }

    #[On('content-revised')]
    public function refreshAfterRevision(): void
    {
        // Publishing in the Studio changes what may be shared; re-render.
    }

    public function setPublic(bool $public, SetContentPublicAccess $action): void
    {
        $content = $action->execute($this->content(), $this->user(), $public ? SpaceContent::ACCESS_PUBLIC : SpaceContent::ACCESS_PRIVATE);
        $this->status = $content->public_access === SpaceContent::ACCESS_PUBLIC
            ? __('public_content.share.made_public')
            : __('public_content.share.made_private');
        $this->createdInvitationUuid = null;
    }

    /** Only me / My contacts: who may read the published edition besides the author's place. */
    public function setContacts(bool $on, SetContentPublicAccess $action): void
    {
        $content = $action->execute($this->content(), $this->user(), $on ? SpaceContent::ACCESS_CONTACTS : SpaceContent::ACCESS_PRIVATE);
        $this->status = $content->public_access === SpaceContent::ACCESS_CONTACTS
            ? __('public_content.share.made_contacts')
            : __('public_content.share.made_private');
        $this->createdInvitationUuid = null;
    }

    public function createInvitation(CreateContentInvitation $action): void
    {
        $invitation = $action->execute($this->content(), $this->user(), $this->inviteeName, $this->inviteMessage !== '' ? $this->inviteMessage : null, $this->expiresInDays);
        $this->status = __('public_content.share.created', ['name' => $invitation->invitee_name]);
        $this->createdInvitationUuid = $invitation->uuid;
        $this->reset('inviteeName', 'inviteMessage', 'expiresInDays');
        $this->resetErrorBag();
    }

    public function revoke(string $uuid, RevokeContentInvitation $action): void
    {
        $invitation = ContentInvitation::query()
            ->where('uuid', $uuid)
            ->where('space_content_id', $this->contentId)
            ->firstOrFail();
        $action->execute($invitation, $this->user());
        $this->status = __('public_content.share.revoked');
        if ($this->createdInvitationUuid === $uuid) {
            $this->createdInvitationUuid = null;
        }
    }

    public function render(): View
    {
        $content = $this->content();
        $user = $this->user();
        $canShare = Gate::forUser($user)->allows('share', $content);
        $canManage = Gate::forUser($user)->allows('manageSharing', $content);

        $invitations = $canManage
            ? $content->invitations()->latest('id')->limit(50)->get()
            : collect();

        return view('livewire.content.share-panel', [
            'content' => $content,
            'canShare' => $canShare,
            'canManage' => $canManage,
            'isPublished' => $content->status === 'published' && $content->active_revision_id !== null,
            'isPublic' => $content->public_access === SpaceContent::ACCESS_PUBLIC,
            'isContacts' => $content->public_access === SpaceContent::ACCESS_CONTACTS,
            'canContacts' => $canShare && $content->context->kind === ContextKind::Personal,
            'publicUrl' => route('public-content.show', $content),
            'indexEnabled' => (bool) config('public_content.index'),
            'invitations' => $invitations,
            'expiryOptions' => CreateContentInvitation::EXPIRY_DAYS,
            'timezone' => TemporalPreferences::timezoneFor($user),
        ]);
    }

    private function content(): SpaceContent
    {
        return SpaceContent::query()->with('context')->findOrFail($this->contentId);
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
