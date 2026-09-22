<?php

namespace Database\Seeders;

use App\Actions\Content\CreateContentEvidenceReference;
use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Actions\Contexts\EnsureAdmissionContext;
use App\Actions\Contexts\EnsureGroupSpaceContext;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\TransitionGroupMembership;
use App\Actions\Interactions\ActivateInteractionDefinitionVersion;
use App\ContentEvidenceTarget;
use App\GroupRoleKey;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\ContentBlueprint;
use App\Models\ContentBlueprintVersion;
use App\Models\ContentEvidenceReference;
use App\Models\Context;
use App\Models\Group;
use App\Models\InteractionDefinition;
use App\Models\InteractionDefinitionVersion;
use App\Models\SpaceContent;
use App\Models\User;
use Illuminate\Database\Seeder;

class Phase7InteractionDemoSeeder extends Seeder
{
    private const OWNER_EMAIL = 'test@example.com';

    private const LEARNER_EMAIL = 'phase7.learner@example.com';

    private const CANDIDATE_EMAIL = 'phase7.candidate@example.com';

    private const SCHOOL_GROUP = 'Phase 7 School Interaction Lab';

    private const HIRING_GROUP = 'Phase 7 Employment Application Lab';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Phase7InteractionDemoSeeder is intended for local/testing environments only.');

            return;
        }

        $this->call(DatabaseSeeder::class);
        app(EnsureSystemContentBlueprints::class)->execute();

        $owner = User::query()->with('actor')->where('email', self::OWNER_EMAIL)->firstOrFail();
        abort_unless($owner->actor instanceof Actor, 422, 'The local demo owner requires an Actor.');

        $learner = $this->demoUser('phase7learner', self::LEARNER_EMAIL, 'en');
        $candidate = $this->demoUser('phase7candidate', self::CANDIDATE_EMAIL, 'fa');

        [$examContext, $examContent, $examInteraction] = $this->ensureSchoolExam(
            $owner,
            $owner->actor,
            $learner,
        );

        [$admission, $applicationContext, $applicationContent, $applicationInteraction] = $this->ensureEmploymentApplication(
            $owner,
            $owner->actor,
            $candidate,
        );

        $portfolio = $this->ensureCandidatePortfolio($candidate);

        $this->command?->newLine();
        $this->command?->info('Phase 7 interaction demo seeded successfully.');
        $this->command?->line('All demo passwords: password');
        $this->command?->line('Reviewer/teacher: '.self::OWNER_EMAIL);
        $this->command?->line('Learner: '.self::LEARNER_EMAIL);
        $this->command?->line('Candidate (Persian locale): '.self::CANDIDATE_EMAIL);
        $this->command?->newLine();
        $this->command?->line('School exam: '.route('contexts.contents.show', [$examContext, $examContent]));
        $this->command?->line('School review queue: '.route('contexts.submissions.index', $examContext));
        $this->command?->line('Employment Admission: '.route('admissions.show', $admission));
        $this->command?->line('Employment application: '.route('contexts.contents.show', [$applicationContext, $applicationContent]));
        $this->command?->line('Employment review queue: '.route('contexts.submissions.index', $applicationContext));
        $this->command?->line('Candidate portfolio evidence UUID: '.$portfolio->uuid);
        $this->command?->line('Candidate portfolio evidence: '.route('content-evidence.show', $portfolio));
        $this->command?->line('Exam interaction UUID: '.$examInteraction->uuid);
        $this->command?->line('Application interaction UUID: '.$applicationInteraction->uuid);
    }

    /** @return array{Context, SpaceContent, InteractionDefinition} */
    private function ensureSchoolExam(User $owner, Actor $ownerActor, User $learner): array
    {
        $group = $this->ensureGroup(
            $ownerActor,
            self::SCHOOL_GROUP,
            'Local proof that one structured-interaction kernel can power a school exam.',
        );
        $this->ensureMember($group, $learner->actor()->firstOrFail(), $ownerActor);

        $space = $group->spaces()->orderBy('id')->firstOrFail();
        $context = app(EnsureGroupSpaceContext::class)->execute($space);

        $content = $this->ensurePublishedContent(
            $context,
            $owner,
            'questionnaire-shell',
            'Phase 7 — Laravel Fundamentals Exam',
            [
                'introduction' => 'Answer the questions below. Draft answers remain private until you explicitly submit the exam.',
            ],
        );

        $interaction = $this->ensureInteraction(
            $context,
            $content,
            $ownerActor,
            'School exam',
            'exam',
            'Laravel Fundamentals Exam',
            'Complete every required answer, then submit. The submitted attempt becomes immutable evidence.',
            [
                [
                    'key' => 'http_method',
                    'label' => 'Which HTTP method is normally used to create a resource?',
                    'type' => 'single_choice',
                    'required' => true,
                    'options' => ['GET', 'POST', 'DELETE'],
                ],
                [
                    'key' => 'explanation',
                    'label' => 'Explain why authorization belongs on the server.',
                    'type' => 'long_text',
                    'required' => true,
                    'constraints' => ['min_length' => 20, 'max_length' => 1500],
                ],
                [
                    'key' => 'confidence',
                    'label' => 'Confidence from 0 to 100',
                    'type' => 'number',
                    'required' => false,
                    'constraints' => ['min' => 0, 'max' => 100],
                ],
            ],
            ['allow_withdrawal' => false, 'max_attempts' => 1],
            [
                'mode' => 'manual',
                'score_max' => 100,
                'criteria' => [
                    ['key' => 'correctness', 'label' => 'Correctness', 'score_max' => 70],
                    ['key' => 'reasoning', 'label' => 'Reasoning', 'score_max' => 30],
                ],
            ],
        );

        return [$context, $content, $interaction];
    }

    /** @return array{Admission, Context, SpaceContent, InteractionDefinition} */
    private function ensureEmploymentApplication(User $owner, Actor $ownerActor, User $candidate): array
    {
        $group = $this->ensureGroup(
            $ownerActor,
            self::HIRING_GROUP,
            'Local proof of a pre-membership employment application in an Admission Context.',
        );

        $candidateActor = $candidate->actor()->firstOrFail();
        $admission = Admission::query()
            ->where('group_id', $group->id)
            ->where('candidate_actor_id', $candidateActor->id)
            ->whereNotIn('status', ['finalized', 'rejected', 'cancelled'])
            ->first();

        if (! $admission instanceof Admission) {
            $admission = Admission::query()->create([
                'group_id' => $group->id,
                'candidate_actor_id' => $candidateActor->id,
                'source_invitation_id' => null,
                'status' => 'draft',
            ]);
            $admission->transitionTo('submitted', $candidateActor, 'Phase 7 demo application started.');
            $admission->transitionTo('under_review', $ownerActor, 'Phase 7 demo review opened.');
        }

        $context = app(EnsureAdmissionContext::class)->execute($admission, $candidate);

        $content = $this->ensurePublishedContent(
            $context,
            $owner,
            'article',
            'Phase 7 — Backend Developer Application',
            [
                'summary' => 'A structured application that stays separate from Admission approval and future Conversation.',
                'body' => 'Complete the application below. You may attach a CV and cite exact immutable portfolio evidence from your Personal Context.',
            ],
        );

        $interaction = $this->ensureInteraction(
            $context,
            $content,
            $ownerActor,
            'Employment application',
            'application',
            'Backend Developer Application',
            'Your draft is private until submit. Submission does not itself approve the Admission or create Membership.',
            [
                [
                    'key' => 'motivation',
                    'label' => 'Why would you like to work with this group?',
                    'type' => 'long_text',
                    'required' => true,
                    'constraints' => ['min_length' => 20, 'max_length' => 3000],
                ],
                [
                    'key' => 'experience_years',
                    'label' => 'Years of relevant experience',
                    'type' => 'number',
                    'required' => true,
                    'constraints' => ['min' => 0, 'max' => 80],
                ],
                [
                    'key' => 'availability',
                    'label' => 'Preferred working days',
                    'type' => 'multiple_choice',
                    'required' => true,
                    'options' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                ],
                [
                    'key' => 'cv',
                    'label' => 'CV / résumé',
                    'type' => 'asset',
                    'required' => true,
                ],
                [
                    'key' => 'portfolio',
                    'label' => 'Exact portfolio evidence',
                    'type' => 'content_evidence',
                    'required' => false,
                ],
            ],
            ['allow_withdrawal' => true, 'max_attempts' => 3],
            [
                'mode' => 'manual',
                'score_max' => 100,
                'criteria' => [
                    ['key' => 'experience', 'label' => 'Relevant experience', 'score_max' => 40],
                    ['key' => 'evidence', 'label' => 'Evidence quality', 'score_max' => 30],
                    ['key' => 'communication', 'label' => 'Communication', 'score_max' => 30],
                ],
            ],
        );

        return [$admission, $context, $content, $interaction];
    }

    private function ensureCandidatePortfolio(User $candidate): ContentEvidenceReference
    {
        $context = app(EnsurePersonalContext::class)->execute($candidate);

        $content = $this->ensurePublishedContent(
            $context,
            $candidate,
            'evidence-work-sample',
            'Phase 7 — Candidate Portfolio Evidence',
            [
                'work_date' => null,
                'role' => 'Backend developer',
                'summary' => 'Implemented a Laravel feature with authorization, tests, and immutable evidence.',
                'outcome' => 'Reviewed locally as a Phase 7 portfolio example.',
            ],
        );

        $revision = $content->activeRevisionRecord();
        abort_unless($revision !== null && $revision->hasVerifiableManifest(), 500);

        $existing = ContentEvidenceReference::query()
            ->where('space_content_revision_id', $revision->id)
            ->where('target_type', ContentEvidenceTarget::Revision->value)
            ->where('created_by_actor_id', $candidate->actor()->firstOrFail()->id)
            ->first();

        return $existing instanceof ContentEvidenceReference
            ? $existing
            : app(CreateContentEvidenceReference::class)->execute(
                $content,
                $revision,
                $candidate,
                ContentEvidenceTarget::Revision,
            );
    }

    private function demoUser(string $username, string $email, string $locale): User
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            $user = User::factory()->create([
                'username' => $username,
                'email' => $email,
                'locale' => $locale,
            ]);
        }

        $user->actor()->firstOrCreate([]);

        return $user->refresh()->load('actor');
    }

    private function ensureGroup(Actor $owner, string $name, string $description): Group
    {
        $existing = Group::query()
            ->where('created_by_actor_id', $owner->id)
            ->where('name', $name)
            ->first();

        return $existing instanceof Group
            ? $existing
            : app(CreateGroup::class)->execute($owner, $name, $description, 'UTC');
    }

    private function ensureMember(Group $group, Actor $actor, Actor $owner): void
    {
        $membership = $group->memberships()->where('actor_id', $actor->id)->first();

        if ($membership === null) {
            $membership = $group->memberships()->create([
                'actor_id' => $actor->id,
                'status' => 'active',
            ]);
            app(TransitionGroupMembership::class)->recordInitial(
                $membership,
                $owner,
                'Phase 7 demo learner membership established.',
            );
        }

        abort_unless($membership->status === 'active', 422, 'Phase 7 demo learner membership must be active.');

        $roles = app(GroupRoleProvisioner::class);
        $roles->grant($actor, $group, $roles->builtInRole($group, GroupRoleKey::Member));
    }

    /** @param array<string, mixed> $payload */
    private function ensurePublishedContent(
        Context $context,
        User $author,
        string $blueprintSlug,
        string $title,
        array $payload,
    ): SpaceContent {
        $existing = SpaceContent::query()
            ->where('context_id', $context->id)
            ->whereHas('revisions', fn ($query) => $query->where('title', $title))
            ->first();

        if ($existing instanceof SpaceContent) {
            return $existing;
        }

        $blueprint = ContentBlueprint::query()
            ->where('scope', ContentBlueprint::SCOPE_SYSTEM)
            ->where('slug', $blueprintSlug)
            ->firstOrFail();
        $version = $blueprint->activeVersionRecord();
        abort_unless($version instanceof ContentBlueprintVersion, 500);

        $content = app(CreateContentFromBlueprint::class)->execute(
            $context,
            $version,
            $author,
            $title,
            $payload,
        );

        return app(PublishSpaceContent::class)->execute($content, $author);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $evaluationConfig
     */
    private function ensureInteraction(
        Context $context,
        SpaceContent $content,
        Actor $manager,
        string $name,
        string $purpose,
        string $title,
        string $instructions,
        array $items,
        array $settings,
        array $evaluationConfig,
    ): InteractionDefinition {
        $existing = InteractionDefinition::query()
            ->where('context_id', $context->id)
            ->where('space_content_id', $content->id)
            ->where('name', $name)
            ->first();

        if ($existing instanceof InteractionDefinition && $existing->activeVersionRecord() !== null) {
            return $existing->refresh()->load('activeVersion');
        }

        $definition = $existing ?? InteractionDefinition::query()->create([
            'context_id' => $context->id,
            'space_content_id' => $content->id,
            'name' => $name,
            'created_by_actor_id' => $manager->id,
        ]);

        $revision = $content->activeRevisionRecord();
        abort_unless($revision !== null && $revision->hasVerifiableManifest(), 500);

        $version = $definition->versions()->where('version', 1)->first();
        if (! $version instanceof InteractionDefinitionVersion) {
            $version = $definition->versions()->create([
                'version' => 1,
                'purpose_key' => $purpose,
                'title' => $title,
                'instructions' => $instructions,
                'items' => $items,
                'settings' => $settings,
                'evaluation_config' => $evaluationConfig,
                'space_content_revision_id' => $revision->id,
                'created_by_actor_id' => $manager->id,
            ]);
        }

        app(ActivateInteractionDefinitionVersion::class)->execute(
            $definition,
            $version,
            $manager->user()->firstOrFail(),
        );

        return $definition->refresh()->load('activeVersion');
    }
}
