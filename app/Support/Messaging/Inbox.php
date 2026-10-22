<?php

namespace App\Support\Messaging;

use App\ContextKind;
use App\Models\Actor;
use App\Models\Context;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Support\ContextPresenter;
use App\Support\RequestMemo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Every conversation a person takes part in — private, group, business,
 * deal or work — with its last message and unread count. Access is checked
 * again on every read, so leaving a group removes its chat from the list.
 */
class Inbox
{
    public const LIMIT = 60;

    /**
     * @return Collection<int, array{conversation: Conversation, participant: ConversationParticipant, title: string, kind: string, other: ?Actor, last: ?ConversationMessage, unread: int}>
     */
    public function for(User $user, bool $archived = false): Collection
    {
        $actor = RequestMemo::user($user)?->actor;
        if (! $actor instanceof Actor) {
            return collect();
        }

        $participants = ConversationParticipant::query()
            ->where('actor_id', $actor->id)
            ->when($archived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->with([
                'conversation.context.directBinding.actorOne.user',
                'conversation.context.directBinding.actorTwo.user',
                ...array_map(fn (string $relation): string => 'conversation.context.'.$relation, ContextPresenter::RELATIONS),
            ])
            ->get()
            ->filter(fn (ConversationParticipant $p): bool => $p->conversation !== null && $this->canView($user, $p->conversation->context));

        if ($participants->isEmpty()) {
            return collect();
        }

        $conversationIds = $participants->pluck('conversation_id')->all();
        $lastIds = ConversationMessage::query()->whereIn('conversation_id', $conversationIds)
            ->groupBy('conversation_id')->select('conversation_id', DB::raw('max(id) as last_id'))->pluck('last_id', 'conversation_id');
        $lastMessages = ConversationMessage::query()->whereIn('id', $lastIds->values())->with(['author.user', 'latestRevision'])->get()->keyBy('conversation_id');
        $unread = $this->unreadCounts($actor, $participants);

        $rows = collect();
        foreach ($participants as $p) {
            $conversation = $p->conversation;
            if (! $conversation instanceof Conversation) {
                continue;
            }
            $context = $conversation->context;
            $binding = $context->directBinding;
            $other = $context->kind === ContextKind::Direct && $binding !== null
                ? ((int) $binding->actor_one_id === (int) $actor->id ? $binding->actorTwo : $binding->actorOne)
                : null;
            $rows->push([
                'conversation' => $conversation,
                'participant' => $p,
                'title' => $other instanceof Actor ? (string) ($other->user->username ?? '') : $this->contextTitle($context),
                'kind' => (string) $context->kind->value,
                'other' => $other,
                'last' => $lastMessages->get($p->conversation_id),
                'unread' => (int) ($unread[$p->conversation_id] ?? 0),
            ]);
        }

        return $rows
            ->sortByDesc(fn (array $row): array => [$row['participant']->pinned_at !== null ? 1 : 0, (int) ($row['last']->id ?? 0)])
            ->take(self::LIMIT)
            ->values();
    }

    /** Unread messages in conversations that are neither muted nor archived (for the badge). */
    public function unreadTotal(User $user): int
    {
        $actor = RequestMemo::user($user)?->actor;
        if (! $actor instanceof Actor) {
            return 0;
        }
        $participants = ConversationParticipant::query()->where('actor_id', $actor->id)->whereNull('archived_at')
            ->where(fn ($q) => $q->whereNull('muted_until')->orWhere('muted_until', '<', now()))
            ->with('conversation.context.directBinding')
            ->get()
            ->filter(fn (ConversationParticipant $p): bool => $p->conversation !== null && $this->canView($user, $p->conversation->context));

        return array_sum($this->unreadCounts($actor, $participants));
    }

    /**
     * @param  Collection<int, ConversationParticipant>  $participants
     * @return array<int, int> conversation id => unread
     */
    private function unreadCounts(Actor $actor, Collection $participants): array
    {
        if ($participants->isEmpty()) {
            return [];
        }

        return DB::table('conversation_messages as m')
            ->join('conversation_participants as p', function ($join) use ($actor): void {
                $join->on('p.conversation_id', '=', 'm.conversation_id')->where('p.actor_id', '=', $actor->id);
            })
            ->whereIn('m.conversation_id', $participants->pluck('conversation_id')->all())
            ->where('m.author_actor_id', '!=', $actor->id)
            ->whereRaw('m.id > coalesce(p.last_read_message_id, 0)')
            ->groupBy('m.conversation_id')
            ->select('m.conversation_id', DB::raw('count(*) as unread'))
            ->pluck('unread', 'm.conversation_id')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * Messages containing the text, in conversations the person can see.
     *
     * @return Collection<int, ConversationMessage>
     */
    public function search(User $user, string $term, int $limit = 30): Collection
    {
        $term = trim($term);
        $actor = RequestMemo::user($user)?->actor;
        if (mb_strlen($term) < 2 || ! $actor instanceof Actor) {
            return collect();
        }
        $visible = ConversationParticipant::query()->where('actor_id', $actor->id)->with('conversation.context.directBinding')->get()
            ->filter(fn (ConversationParticipant $p): bool => $p->conversation !== null && $this->canView($user, $p->conversation->context))
            ->pluck('conversation_id')->all();
        if ($visible === []) {
            return collect();
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return ConversationMessage::query()
            ->whereIn('conversation_id', $visible)
            ->where(fn ($q) => $q->where('body', 'like', $like)->orWhereHas('revisions', fn ($r) => $r->where('kind', 'edit')->where('body', 'like', $like)))
            ->with(['author.user', 'latestRevision', 'conversation'])
            ->latest('id')
            ->limit($limit * 2)
            ->get()
            ->reject(fn (ConversationMessage $m): bool => $m->isDeleted() || ! str_contains(mb_strtolower($m->currentBody()), mb_strtolower($term)))
            ->take($limit)
            ->values();
    }

    public function canView(User $user, ?Context $context): bool
    {
        return $context instanceof Context
            && (bool) RequestMemo::remember('inbox-view:'.$user->id.':'.$context->id, fn (): bool => Gate::forUser($user)->allows('view', $context));
    }

    public function contextTitle(Context $context): string
    {
        return app(ContextPresenter::class)->label($context);
    }
}
