<?php

namespace App\Support;

use App\Models\Actor;
use App\Models\PersonalContext;
use App\Models\PlanOccurrence;
use App\Models\User;
use App\PlanOccurrenceStatus;
use App\Services\Surfaces\ExperienceNavigation;
use Illuminate\Support\Collection;

class HomeGuidanceService
{
    public function __construct(
        private readonly ExperienceNavigation $navigation,
    ) {}

    /**
     * @param array{
     *   todayOccurrences: Collection<int, PlanOccurrence>,
     *   waitingOnMe: Collection<int, HomeActionItem>,
     *   waitingOnOthers: Collection<int, HomeActionItem>,
     *   activeIntents: Collection,
     *   activeRelationships: Collection,
     *   groupMemberships: Collection,
     *   obligations: Collection
     * } $projection
     * @return array{
     *   onboarding: array{completed:int,total:int,percent:int,complete:bool,steps:list<HomeOnboardingStep>},
     *   nextAction: ?HomeGuidanceItem,
     *   attentionCount: int,
     *   firstGoalChoices: list<HomeGuidanceItem>
     * }
     */
    public function build(User $user, array $projection): array
    {
        $current = User::query()
            ->with([
                'actor.profile',
                'actor.contactPoints',
                'actor.addresses',
            ])
            ->find($user->id);

        abort_unless($current instanceof User, 403);

        $actor = $current->actor;
        if (! $actor instanceof Actor) {
            return [
                'onboarding' => [
                    'completed' => 0,
                    'total' => 1,
                    'percent' => 0,
                    'complete' => false,
                    'steps' => [
                        new HomeOnboardingStep(
                            key: 'participant_identity',
                            title: (string) __('home.onboarding.identity_pending_title'),
                            summary: (string) __('home.onboarding.identity_pending_help'),
                            url: route('profile.edit'),
                            cta: (string) __('home.onboarding.open_profile'),
                            complete: false,
                        ),
                    ],
                ],
                'nextAction' => null,
                'attentionCount' => 0,
                'firstGoalChoices' => [],
            ];
        }

        $surfaceKeys = $this->accessibleSurfaceKeys($current);
        $firstGoalChoices = $this->firstGoalChoices($surfaceKeys);
        $hasFirstGoal = $this->hasFirstGoal($actor, $projection);

        $steps = [
            new HomeOnboardingStep(
                key: 'identity',
                title: (string) __('home.onboarding.identity_title'),
                summary: (string) __('home.onboarding.identity_help'),
                url: route('profile.edit'),
                cta: (string) __('home.onboarding.identity_cta'),
                complete: filled($actor->profile?->display_name),
            ),
            new HomeOnboardingStep(
                key: 'contact',
                title: (string) __('home.onboarding.contact_title'),
                summary: (string) __('home.onboarding.contact_help'),
                url: route('profile.contacts.index'),
                cta: (string) __('home.onboarding.contact_cta'),
                complete: $actor->contactPoints->isNotEmpty() || $actor->addresses->isNotEmpty(),
            ),
            new HomeOnboardingStep(
                key: 'first_goal',
                title: (string) __('home.onboarding.first_goal_title'),
                summary: (string) __('home.onboarding.first_goal_help'),
                url: $firstGoalChoices[0]->url ?? route('dashboard'),
                cta: (string) __('home.onboarding.first_goal_cta'),
                complete: $hasFirstGoal,
            ),
        ];

        $completed = collect($steps)->filter(fn (HomeOnboardingStep $step): bool => $step->complete)->count();
        $total = count($steps);

        $onboarding = [
            'completed' => $completed,
            'total' => $total,
            'percent' => $total === 0 ? 100 : (int) round(($completed / $total) * 100),
            'complete' => $completed === $total,
            'steps' => $steps,
        ];

        return [
            'onboarding' => $onboarding,
            'nextAction' => $this->nextAction(
                $current,
                $projection,
                $steps,
                $firstGoalChoices,
                $surfaceKeys,
            ),
            'attentionCount' => $projection['waitingOnMe']->count(),
            'firstGoalChoices' => $hasFirstGoal ? [] : $firstGoalChoices,
        ];
    }

    /**
     * @param array{
     *   todayOccurrences: Collection<int, PlanOccurrence>,
     *   waitingOnMe: Collection<int, HomeActionItem>,
     *   activeIntents: Collection,
     *   activeRelationships: Collection,
     *   obligations: Collection
     * } $projection
     * @param list<HomeOnboardingStep> $steps
     * @param list<HomeGuidanceItem> $firstGoalChoices
     * @param Collection<int, string> $surfaceKeys
     */
    private function nextAction(
        User $user,
        array $projection,
        array $steps,
        array $firstGoalChoices,
        Collection $surfaceKeys,
    ): ?HomeGuidanceItem {
        $waiting = $projection['waitingOnMe']->first();
        if ($waiting instanceof HomeActionItem) {
            return new HomeGuidanceItem(
                key: 'attention:'.$waiting->key,
                kind: 'attention',
                title: $waiting->title,
                summary: $waiting->summary ?: (string) __('home.guidance.attention_reason'),
                consequence: (string) __('home.guidance.consequence.'.$waiting->kind),
                url: $waiting->url,
                cta: (string) __('home.guidance.review_now'),
            );
        }

        $occurrence = $projection['todayOccurrences']
            ->first(fn (PlanOccurrence $candidate): bool => in_array(
                $candidate->status,
                [PlanOccurrenceStatus::InProgress, PlanOccurrenceStatus::Scheduled],
                true,
            ));

        if ($occurrence instanceof PlanOccurrence) {
            return new HomeGuidanceItem(
                key: 'occurrence:'.$occurrence->uuid,
                kind: 'schedule',
                title: $occurrence->plan->title,
                summary: $occurrence->status === PlanOccurrenceStatus::InProgress
                    ? (string) __('home.guidance.in_progress_reason')
                    : (string) __('home.guidance.scheduled_reason'),
                consequence: (string) __('home.guidance.schedule_consequence'),
                url: route('planner.show', $occurrence->plan).'#occurrence-'.$occurrence->uuid,
                cta: (string) __('home.guidance.open_plan'),
            );
        }

        $setupStep = collect($steps)
            ->first(fn (HomeOnboardingStep $step): bool => ! $step->complete && $step->key !== 'first_goal');

        if ($setupStep instanceof HomeOnboardingStep) {
            return new HomeGuidanceItem(
                key: 'onboarding:'.$setupStep->key,
                kind: 'setup',
                title: $setupStep->title,
                summary: $setupStep->summary,
                consequence: (string) __('home.guidance.setup_consequence'),
                url: $setupStep->url,
                cta: $setupStep->cta,
            );
        }

        $outstanding = $projection['obligations']->sum(
            fn (array $bucket): int => (int) $bucket['receivable_outstanding_minor']
                + (int) $bucket['payable_outstanding_minor'],
        );

        if ($outstanding > 0 && ($surfaceKeys->contains('money') || $surfaceKeys->contains('accounting'))) {
            return new HomeGuidanceItem(
                key: 'money:outstanding',
                kind: 'money',
                title: (string) __('home.guidance.money_title'),
                summary: (string) __('home.guidance.money_reason'),
                consequence: (string) __('home.guidance.money_consequence'),
                url: route('money.index'),
                cta: (string) __('home.guidance.open_money'),
            );
        }

        $intent = $projection['activeIntents']->first();
        if ($intent !== null && $surfaceKeys->contains('market')) {
            return new HomeGuidanceItem(
                key: 'matches:'.$intent->uuid,
                kind: 'matches',
                title: (string) __('home.guidance.matches_title', [
                    'title' => $intent->title ?: $intent->concept->displayLabel(),
                ]),
                summary: (string) __('home.guidance.matches_reason'),
                consequence: (string) __('home.guidance.matches_consequence'),
                url: route('intents.matches', $intent),
                cta: (string) __('home.guidance.open_matches'),
            );
        }

        $relationship = $projection['activeRelationships']->first();
        if ($relationship !== null && $surfaceKeys->contains('deals')) {
            return new HomeGuidanceItem(
                key: 'deal:'.$relationship->uuid,
                kind: 'work',
                title: $relationship->title ?: $relationship->purposeConcept->displayLabel(),
                summary: (string) __('home.guidance.active_work_reason'),
                consequence: (string) __('home.guidance.active_work_consequence'),
                url: route('relationships.show', $relationship),
                cta: (string) __('home.guidance.open_work'),
            );
        }

        if ($firstGoalChoices !== []) {
            $first = $firstGoalChoices[0];

            return new HomeGuidanceItem(
                key: 'first-goal:'.$first->key,
                kind: 'start',
                title: (string) __('home.guidance.start_title'),
                summary: (string) __('home.guidance.start_reason'),
                consequence: (string) __('home.guidance.start_consequence'),
                url: $first->url,
                cta: $first->cta,
            );
        }

        if ($surfaceKeys->contains('planner')) {
            return new HomeGuidanceItem(
                key: 'review-planner',
                kind: 'review',
                title: (string) __('home.guidance.review_day_title'),
                summary: (string) __('home.guidance.review_day_reason'),
                consequence: (string) __('home.guidance.review_day_consequence'),
                url: route('planner.index'),
                cta: (string) __('home.guidance.open_planner'),
            );
        }

        return null;
    }

    /**
     * @param Collection<int, string> $surfaceKeys
     * @return list<HomeGuidanceItem>
     */
    private function firstGoalChoices(Collection $surfaceKeys): array
    {
        $items = [];

        if ($surfaceKeys->contains('market')) {
            $items[] = new HomeGuidanceItem(
                key: 'need-offer',
                kind: 'goal',
                title: (string) __('home.goals.need_offer_title'),
                summary: (string) __('home.goals.need_offer_help'),
                consequence: (string) __('home.goals.need_offer_consequence'),
                url: route('intents.create'),
                cta: (string) __('home.goals.need_offer_cta'),
            );
        }

        if ($surfaceKeys->contains('planner')) {
            $items[] = new HomeGuidanceItem(
                key: 'plan',
                kind: 'goal',
                title: (string) __('home.goals.plan_title'),
                summary: (string) __('home.goals.plan_help'),
                consequence: (string) __('home.goals.plan_consequence'),
                url: route('planner.create'),
                cta: (string) __('home.goals.plan_cta'),
            );
        }

        if ($surfaceKeys->contains('business')) {
            $items[] = new HomeGuidanceItem(
                key: 'business',
                kind: 'goal',
                title: (string) __('home.goals.business_title'),
                summary: (string) __('home.goals.business_help'),
                consequence: (string) __('home.goals.business_consequence'),
                url: route('businesses.create'),
                cta: (string) __('home.goals.business_cta'),
            );
        }

        if ($surfaceKeys->contains('groups')) {
            $items[] = new HomeGuidanceItem(
                key: 'group',
                kind: 'goal',
                title: (string) __('home.goals.group_title'),
                summary: (string) __('home.goals.group_help'),
                consequence: (string) __('home.goals.group_consequence'),
                url: route('groups.index'),
                cta: (string) __('home.goals.group_cta'),
            );
        }

        if ($surfaceKeys->contains('content')) {
            $items[] = new HomeGuidanceItem(
                key: 'content',
                kind: 'goal',
                title: (string) __('home.goals.content_title'),
                summary: (string) __('home.goals.content_help'),
                consequence: (string) __('home.goals.content_consequence'),
                url: route('contexts.personal'),
                cta: (string) __('home.goals.content_cta'),
            );
        }

        return $items;
    }

    /**
     * @param array{
     *   activeIntents: Collection,
     *   activeRelationships: Collection,
     *   groupMemberships: Collection
     * } $projection
     */
    private function hasFirstGoal(Actor $actor, array $projection): bool
    {
        if ($projection['activeIntents']->isNotEmpty() || $projection['activeRelationships']->isNotEmpty()) {
            return true;
        }

        if ($actor->createdPlans()->exists() || $actor->planParticipations()->exists()) {
            return true;
        }

        if ($actor->businessMemberships()->where('status', 'active')->exists()) {
            return true;
        }

        if ($projection['groupMemberships']->isNotEmpty()) {
            return true;
        }

        return PersonalContext::query()
            ->where('actor_id', $actor->id)
            ->whereHas('context.contents')
            ->exists();
    }

    /** @return Collection<int, string> */
    private function accessibleSurfaceKeys(User $user): Collection
    {
        $navigation = $this->navigation->for($user);

        return collect($navigation['primary'])
            ->flatMap(fn (array $destination): Collection => collect($destination['items'])->pluck('key'))
            ->merge(collect($navigation['account'])->pluck('key'))
            ->merge(collect($navigation['help'])->pluck('key'))
            ->merge(collect($navigation['labs'])->pluck('key'))
            ->merge(collect($navigation['admin'])->pluck('key'))
            ->unique()
            ->values();
    }
}
