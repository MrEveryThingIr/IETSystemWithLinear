<?php

namespace App\Livewire\Contexts;

use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Models\Context;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Context Content')]
class ContentShow extends Component
{
    public Context $context;

    public SpaceContent $content;

    public string $title = '';

    public string $body = '';

    public function mount(Context $context, SpaceContent $content): void
    {
        abort_unless((int) $content->context_id === (int) $context->id, 404);
        Gate::forUser($this->user())->authorize('view', $content);

        $this->context = $context;
        $this->content = $content;
        $this->fillFromVisibleRevision();
    }

    public function save(ReviseSpaceContent $revise): void
    {
        Gate::forUser($this->user())->authorize('update', $this->content);

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $this->content = $revise->execute(
            $this->content,
            $this->user(),
            $this->title,
            ['body' => $this->body],
        );

        $this->fillFromVisibleRevision();
        session()->flash('status', __('ui.content.revised'));
    }

    public function publish(PublishSpaceContent $publish): void
    {
        Gate::forUser($this->user())->authorize('publish', $this->content);
        $this->content = $publish->execute($this->content, $this->user());
        $this->fillFromVisibleRevision();
        session()->flash('status', __('ui.content.published'));
    }

    public function render(): View
    {
        $current = SpaceContent::query()
            ->with(['author.user', 'definition'])
            ->findOrFail($this->content->id);

        abort_unless((int) $current->context_id === (int) $this->context->id, 404);
        Gate::forUser($this->user())->authorize('view', $current);
        $this->content = $current;

        $canUpdate = Gate::forUser($this->user())->allows('update', $current);
        $canPublish = Gate::forUser($this->user())->allows('publish', $current);
        $revision = $this->visibleRevision($canUpdate);
        $body = is_string($revision->payload['body'] ?? null) ? $revision->payload['body'] : null;

        return view('livewire.contexts.content-show', compact(
            'revision',
            'body',
            'canUpdate',
            'canPublish',
        ));
    }

    private function fillFromVisibleRevision(): void
    {
        $canUpdate = Gate::forUser($this->user())->allows('update', $this->content);
        $revision = $this->visibleRevision($canUpdate);
        $this->title = $revision->title;
        $this->body = is_string($revision->payload['body'] ?? null) ? $revision->payload['body'] : '';
    }

    private function visibleRevision(bool $canSeeDraft): SpaceContentRevision
    {
        if ($canSeeDraft) {
            $revision = $this->content->draftRevisionRecord() ?? $this->content->activeRevisionRecord();
            abort_unless($revision instanceof SpaceContentRevision, 404);

            return $revision;
        }

        $revision = $this->content->activeRevisionRecord();
        abort_unless($revision instanceof SpaceContentRevision, 404);

        return $revision;
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
