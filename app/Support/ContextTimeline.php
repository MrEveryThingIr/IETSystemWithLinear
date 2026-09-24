<?php

namespace App\Support;

use App\Models\AdmissionEvent;
use App\Models\Context;
use App\Models\ConversationMessage;
use App\Models\RelationshipEvent;
use App\Models\SpaceContentLifecycleEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ContextTimeline
{
    /** @return Collection<int, TimelineEntry> */
    public function entries(Context $context, User $user, int $limit = 200): Collection
    {
        Gate::forUser($user)->authorize('view', $context);

        /** @var Collection<int, TimelineEntry> $entries */
        $entries = collect();

        ConversationMessage::query()
            ->with(['author.user'])
            ->whereHas('conversation', fn ($query) => $query->where('context_id', $context->id))
            ->latest('id')
            ->limit($limit)
            ->get()
            ->each(function (ConversationMessage $message) use ($context, $entries): void {
                $entries->push(new TimelineEntry(
                    key: 'message:'.$message->uuid,
                    kind: 'message',
                    title: (string) __('collaboration.timeline.message'),
                    summary: Str::limit($message->body, 240),
                    occurredAt: $message->created_at ?? now(),
                    actor: $message->author,
                    url: route('contexts.conversation', $context).'#message-'.$message->uuid,
                ));
            });

        SpaceContentLifecycleEvent::query()
            ->with(['content.activeRevision', 'actor.user'])
            ->whereHas('content', fn ($query) => $query->where('context_id', $context->id))
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->each(function (SpaceContentLifecycleEvent $event) use ($context, $entries, $user): void {
                if (! Gate::forUser($user)->allows('view', $event->content)) {
                    return;
                }

                $title = $event->content->activeRevision?->title
                    ?: (string) __('ui.content.untitled');

                $entries->push(new TimelineEntry(
                    key: 'content:'.$event->uuid,
                    kind: 'content',
                    title: (string) __('collaboration.timeline.content', [
                        'event' => Str::headline($event->event_type),
                        'title' => $title,
                    ]),
                    summary: $event->reason,
                    occurredAt: $event->created_at ?? now(),
                    actor: $event->actor,
                    url: route('contexts.contents.show', [$context, $event->content]),
                ));
            });

        $context->loadMissing([
            'relationshipBinding.relationship',
            'admissionBinding.admission',
        ]);

        $relationship = $context->relationshipBinding?->relationship;
        if ($relationship !== null) {
            RelationshipEvent::query()
                ->with('actor.user')
                ->where('relationship_id', $relationship->id)
                ->latest('id')
                ->limit($limit)
                ->get()
                ->each(function (RelationshipEvent $event) use ($relationship, $entries): void {
                    $entries->push(new TimelineEntry(
                        key: 'relationship-event:'.$event->id,
                        kind: 'relationship',
                        title: (string) __('relationships.events.'.$event->event_type->value),
                        summary: null,
                        occurredAt: $event->created_at ?? now(),
                        actor: $event->actor,
                        url: route('relationships.show', $relationship),
                    ));
                });
        }

        $admission = $context->admissionBinding?->admission;
        if ($admission !== null) {
            AdmissionEvent::query()
                ->with('actor.user')
                ->where('admission_id', $admission->id)
                ->latest('id')
                ->limit($limit)
                ->get()
                ->each(function (AdmissionEvent $event) use ($admission, $entries): void {
                    $entries->push(new TimelineEntry(
                        key: 'admission-event:'.$event->id,
                        kind: 'admission',
                        title: (string) __('ui.events.'.str_replace('.', '_', $event->event)),
                        summary: $event->note,
                        occurredAt: $event->created_at ?? now(),
                        actor: $event->actor,
                        url: route('admissions.show', $admission),
                    ));
                });
        }

        return $entries
            ->sort(function (TimelineEntry $left, TimelineEntry $right): int {
                $time = $right->occurredAt->getTimestamp() <=> $left->occurredAt->getTimestamp();

                return $time !== 0 ? $time : strcmp($right->key, $left->key);
            })
            ->take($limit)
            ->values();
    }
}
