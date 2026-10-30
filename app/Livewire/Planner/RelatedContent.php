<?php

namespace App\Livewire\Planner;

use App\Actions\Planner\ManagePlanContent;
use App\Models\Actor;
use App\Models\Plan;
use App\Models\PlanContentLink;
use App\Models\PlanOccurrence;
use App\Models\SpaceContent;
use App\Models\User;
use App\Support\TemporalPreferences;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Related content" of a Plan: Content written for the whole plan or for one of its days, with Add content and Attach
 * existing. It only reads and writes pointers (PlanContentLink); each entry is shown, opened and edited by the Content's
 * own rules, so a draft stays visible only to the people who may see it.
 */
class RelatedContent extends Component
{
    #[Locked]
    public int $planId;

    /** The day new or attached content belongs to; empty = the whole plan. */
    public string $occurrence = '';

    public string $attach = '';

    public function mount(Plan $plan): void
    {
        $this->planId = $plan->id;
        abort_unless(Gate::forUser($this->user())->allows('view', $plan), 403);
    }

    public function attachExisting(ManagePlanContent $links): void
    {
        $content = SpaceContent::query()->where('uuid', $this->attach)->firstOrFail();
        $links->link($this->user(), $this->plan(), $content, $this->chosenOccurrence());
        $this->reset('attach');
    }

    public function unlink(string $uuid, ManagePlanContent $links): void
    {
        $link = PlanContentLink::query()->where('uuid', $uuid)->where('plan_id', $this->planId)->firstOrFail();
        $links->unlink($this->user(), $link);
    }

    public function render(): View
    {
        $user = $this->user();
        $plan = $this->plan();
        $actor = $user->actor;
        abort_unless($actor instanceof Actor, 403);

        $entries = PlanContentLink::query()->where('plan_id', $plan->id)
            ->with(['occurrence', 'content.activeRevision', 'content.draftRevision', 'content.context'])
            ->orderBy('id')->get()
            ->filter(fn (PlanContentLink $link): bool => Gate::forUser($user)->allows('view', $link->content))
            ->map(function (PlanContentLink $link) use ($user, $actor): array {
                $content = $link->content;
                $editable = Gate::forUser($user)->allows('update', $content);
                $revision = $editable ? ($content->draftRevision ?? $content->activeRevision) : $content->activeRevision;

                return [
                    'link' => $link,
                    'title' => $revision?->title ?: __('ui.content.untitled'),
                    'state' => $content->status === 'published' ? 'published' : 'draft',
                    'audience' => $content->status === 'published' ? $content->public_access : 'private',
                    'url' => $editable
                        ? route('contexts.contents.studio', [$content->context, $content])
                        : route('contexts.contents.show', [$content->context, $content]),
                    'mine' => (int) $link->linked_by_actor_id === (int) $actor->id,
                    'date' => $link->occurrence?->local_date,
                ];
            })
            ->sortBy(fn (array $e): string => ($e['date']?->toDateString() ?? '0000-00-00').'|'.str_pad((string) $e['link']->id, 12, '0', STR_PAD_LEFT))
            ->values();

        $canWrite = Gate::forUser($user)->allows('participate', $plan);
        $attachable = $canWrite
            ? SpaceContent::query()->where('author_actor_id', $actor->id)->where('status', '!=', 'archived')
                ->with(['activeRevision', 'draftRevision'])->latest('updated_at')->limit(100)->get()
                ->filter(fn (SpaceContent $c): bool => Gate::forUser($user)->allows('update', $c))
                ->map(fn (SpaceContent $c): array => ['uuid' => $c->uuid, 'title' => ($c->draftRevision ?? $c->activeRevision)?->title ?: __('ui.content.untitled')])
            : collect();

        return view('livewire.planner.related-content', [
            'plan' => $plan,
            'viewer' => $user,
            'displayTimezone' => TemporalPreferences::timezoneFor($user),
            'entries' => $entries,
            'canWrite' => $canWrite,
            'attachable' => $attachable,
            'occurrences' => $canWrite ? $plan->occurrences()->orderBy('scheduled_start_at')->limit(400)->get(['id', 'uuid', 'local_date', 'scheduled_start_at']) : collect(),
        ]);
    }

    private function chosenOccurrence(): ?PlanOccurrence
    {
        return $this->occurrence === '' ? null : PlanOccurrence::query()->where('plan_id', $this->planId)->where('uuid', $this->occurrence)->firstOrFail();
    }

    private function plan(): Plan
    {
        return Plan::query()->findOrFail($this->planId);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
