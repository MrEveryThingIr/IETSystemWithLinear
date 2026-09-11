<?php

namespace Database\Seeders;

use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\ManageGroupAgreement;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\AdmissionEvent;
use App\Models\AgreementAcceptance;
use App\Models\Group;
use App\Models\GroupAgreementVersion;
use App\Models\GroupInvitation;
use App\Models\GroupInvitationAcceptance;
use App\Models\GroupMembership;
use App\Models\GroupRoleChangeRequest;
use App\Models\MembershipAgreementAcceptance;
use App\Models\Responsibility;
use App\Models\Story;
use App\Models\StoryRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class ConstructionProjectSeeder extends Seeder
{
    /** @var list<array{slug: string, name: string, description: string}> */
    private const PROJECTS = [
        [
            'slug' => 'riverside',
            'name' => 'Riverside Residence Build',
            'description' => 'Track structural, architectural, electrical, plumbing, procurement, safety, cost, and handover progress for a twelve-unit residential building.',
        ],
        [
            'slug' => 'al-noor',
            'name' => 'Al Noor Community Center',
            'description' => 'Coordinate the owners, contractors, trades, suppliers, inspections, payment preparation, and daily site progress for a community center.',
        ],
        [
            'slug' => 'greenline',
            'name' => 'Greenline Mixed-Use Renovation',
            'description' => 'Synchronize a live renovation across retail and residential floors while tracking access, safety, procurement, quality, cost, and phased completion.',
        ],
    ];

    /** @var array<string, list<string>> */
    private const PROJECT_ROLES = [
        'Project Manager' => ['manage_group', 'manage_members', 'manage_invitations', 'approve_role_changes', 'participate'],
        'General Contractor' => ['manage_members', 'manage_invitations', 'participate'],
        'Civil Engineer' => ['participate'],
        'Architect' => ['participate'],
        'Electrical Contractor' => ['participate'],
        'Plumbing Contractor' => ['participate'],
        'Site Supervisor' => ['participate'],
        'Safety Officer' => ['participate'],
        'Quantity Surveyor' => ['participate'],
        'Building Materials Supplier' => ['participate'],
        'Skilled Worker' => ['participate'],
        'General Laborer' => ['participate'],
    ];

    public function run(): void
    {
        fake()->seed(20260911);
        $memberCounts = collect(self::PROJECTS)
            ->map(fn (): int => fake()->numberBetween(15, 20));

        foreach (self::PROJECTS as $index => $project) {
            if (Group::query()->where('name', $project['name'])->exists()) {
                continue;
            }

            DB::transaction(function () use ($project, $memberCounts, $index): void {
                $this->seedProject($project, $memberCounts[$index]);
            });
        }

        $this->seedAccountEdgeCases();
    }

    /** @param array{slug: string, name: string, description: string} $project */
    private function seedProject(array $project, int $memberCount): void
    {
        $createGroup = app(CreateGroup::class);
        $roleProvisioner = app(GroupRoleProvisioner::class);

        $owners = collect(range(1, 3))->map(
            fn (int $number): Actor => $this->createActor(
                "{$project['slug']}_partner_{$number}",
                "partner{$number}.{$project['slug']}@example.test",
            ),
        );

        /** @var Actor $creator */
        $creator = $owners->first();
        $group = $createGroup->execute($creator, $project['name'], $project['description']);
        $builtInRoles = $roleProvisioner->provision($group);

        $owners->skip(1)->each(function (Actor $owner) use ($group, $roleProvisioner, $builtInRoles): void {
            GroupMembership::factory()->for($group)->for($owner)->create();
            $roleProvisioner->assign($owner, $group, $builtInRoles['owner']);
        });

        $projectRoles = collect(self::PROJECT_ROLES)->mapWithKeys(
            fn (array $permissions, string $name): array => [$name => $roleProvisioner->createRole($group, $name, $permissions)],
        );

        $workers = collect(range(1, $memberCount - 3))->map(function (int $number) use ($project, $group, $projectRoles, $roleProvisioner): Actor {
            $actor = $this->createActor(
                "{$project['slug']}_member_{$number}",
                "member{$number}.{$project['slug']}@example.test",
            );
            GroupMembership::factory()->for($group)->for($actor)->create();
            /** @var Role $role */
            $role = $projectRoles->values()[($number - 1) % $projectRoles->count()];
            $roleProvisioner->assign($actor, $group, $role);

            return $actor;
        });

        $members = $owners->concat($workers)->values();
        $formerUser = User::factory()->suspended()->create([
            'username' => "{$project['slug']}_former_member",
            'email' => "former.{$project['slug']}@example.test",
        ]);
        $formerActor = Actor::factory()->for($formerUser)->create();
        GroupMembership::factory()->for($group)->for($formerActor)->removed()->create();

        $activeVersions = $this->seedAgreements($group, $creator, $project['slug']);
        $this->seedStories($group, $creator, $members);
        $this->seedInvitationEdgeCases($group, $creator, $project['slug']);
        $this->seedAdmissionEdgeCases($group, $creator, $members, $activeVersions, $project['slug']);
        $this->seedRoleChangeRequests($group, $creator, $workers, $projectRoles, $roleProvisioner);
        $this->seedMembershipAgreementAcceptances($group, $members, $activeVersions);
    }

    /** @return Collection<int, GroupAgreementVersion> */
    private function seedAgreements(Group $group, Actor $owner, string $slug): Collection
    {
        $manager = app(ManageGroupAgreement::class);
        $participation = $manager->create(
            $group,
            $owner,
            'Project participation and reporting agreement',
            true,
            'Members must report material progress, delays, safety incidents, inspection outcomes, and blockers in the group. Role assignments define coordination authority but do not replace signed employment, supply, or partnership contracts.',
        );
        /** @var GroupAgreementVersion $participationVersion */
        $participationVersion = $participation->versions()->firstOrFail();
        $manager->propose($participationVersion, $owner);
        $manager->approve($participationVersion, $owner);
        $manager->activate($participationVersion, $owner);

        $safety = $manager->create(
            $group,
            $owner,
            'Site safety and access agreement',
            true,
            'Every participant must follow site induction, personal protective equipment, access-control, incident-reporting, and stop-work requirements. High-risk work requires the responsible supervisor approval before work starts.',
        );
        /** @var GroupAgreementVersion $safetyVersion */
        $safetyVersion = $safety->versions()->firstOrFail();
        $manager->propose($safetyVersion, $owner);
        $manager->approve($safetyVersion, $owner);
        $manager->activate($safetyVersion, $owner);

        $manager->create(
            $group,
            $owner,
            'Payment and settlement planning notes',
            false,
            'Planning placeholder only: payment method, currency, billing unit, measurement approval, retention, tax treatment, due date, dispute process, and final settlement must remain TBD until the future Contract and obligation domains are implemented and signed by the relevant parties.',
        );

        $revision = $manager->revise(
            $participation,
            $owner,
            'Members must publish weekly progress evidence, blockers, decisions, inspection results, and safety incidents. Financial claims require measurement evidence and owner approval, but enforceable obligations remain in a separate signed Contract.',
            'Prepare the reporting agreement for the future Spaces and Content workflow.',
            true,
        );
        $manager->propose($revision, $owner);

        if ($slug === 'al-noor') {
            $manager->approve($revision, $owner);
            $manager->schedule($revision, $owner, now()->addMonth());
        } elseif ($slug === 'greenline') {
            $manager->reject($revision, $owner, 'Clarify who approves measurement evidence before this version is reconsidered.');
        }

        return collect([$participationVersion->refresh(), $safetyVersion->refresh()]);
    }

    /** @param Collection<int, Actor> $members */
    private function seedStories(Group $group, Actor $owner, Collection $members): void
    {
        $roleDescriptions = [
            'Project Manager — coordinates scope, schedule, decisions, risks, and owner reporting.',
            'General Contractor — plans construction methods, subcontractors, labor, sequencing, and site delivery.',
            'Civil Engineer — verifies structural work, technical compliance, measurements, and inspection evidence.',
            'Architect — controls drawings, finishes, spatial decisions, submittals, and design clarifications.',
            'Electrical Contractor — installs and tests power, lighting, grounding, low-voltage, and electrical safety systems.',
            'Plumbing Contractor — installs and tests water, drainage, sanitary, pumping, and fixture systems.',
            'Site Supervisor — coordinates daily work fronts, attendance, materials, equipment, and blockers.',
            'Safety Officer — manages induction, permits, inspections, incidents, corrective actions, and stop-work escalation.',
            'Quantity Surveyor — validates quantities, variations, progress claims, retention, and settlement evidence.',
            'Building Materials Supplier — confirms specifications, quotations, lead times, delivery evidence, and defects replacement.',
            'Skilled Worker — completes assigned trade work to drawings and records quantities, quality checks, and blockers.',
            'General Laborer — supports handling, preparation, housekeeping, access, and supervised construction tasks.',
        ];

        $stories = collect([
            'Project charter and owner-partner governance' => 'Three owner-partners share project oversight. Partner 1 is the originating owner. Reserved decisions: scope change, budget baseline, contractor appointment, major variation, suspension, and final acceptance. Decision thresholds, ownership shares, signing authority, and deadlock resolution are [TBD — future signed Contract].',
            'Role and responsibility matrix' => implode("\n", $roleDescriptions),
            'Payment and settlement placeholders' => "No executable payment obligation is created by this record.\n\nContract reference: [TBD]\nPayee and payer: [TBD]\nCurrency: [TBD]\nPayment method/account: [TBD]\nBilling basis (fixed price, milestone, measured quantity, day work): [TBD]\nMeasurement and approval authority: [TBD]\nInvoice and supporting evidence: [TBD]\nTax and withholding: [TBD]\nRetention percentage/release: [TBD]\nDue date and late-payment treatment: [TBD]\nVariation approval: [TBD]\nDispute and final settlement process: [TBD]\nCurrent settlement status: NOT CONTRACTED.",
            'Progress reporting protocol' => "Daily: labor, completed quantities, deliveries, inspections, incidents, delays, blockers, photos/evidence references, and next-day plan.\nWeekly: planned versus actual milestones, critical path, quality issues, safety actions, procurement risks, change requests, forecast cost, and decisions needed from owners.",
            'Example daily site record' => "Date: [sample]\nWeather/access: normal\nWork completed: ground-floor conduit routing and first-fix plumbing inspection preparation\nLabor: [TBD by trade]\nMaterials received: [TBD delivery reference]\nQuality/safety checks: [TBD evidence]\nBlocker: ceiling coordination drawing needs architect confirmation\nDecision owner: Project Manager\nNext action: publish coordinated drawing and inspection request.",
        ])->map(
            fn (string $body, string $title): Story => Story::factory()->for($group)->for($owner, 'creator')->create(compact('title', 'body')),
        );

        $stories->values()->each(function (Story $story, int $index) use ($members): void {
            $contributors = $members->slice($index % $members->count(), 3)->values();
            if ($contributors->count() < 3) {
                $contributors = $contributors->concat($members->take(3 - $contributors->count()));
            }

            foreach ([Responsibility::Author, Responsibility::Editor, Responsibility::Contributor] as $position => $responsibility) {
                StoryRole::factory()->for($story)->for($contributors[$position])->create(compact('responsibility'));
            }
        });
    }

    private function seedInvitationEdgeCases(Group $group, Actor $owner, string $slug): void
    {
        $base = GroupInvitation::factory()->for($group)->for($owner, 'inviter');

        $base->unlimited()->create(['token' => "demo-{$slug}-reusable"]);
        $base->targeted("future.worker.{$slug}@example.test")->create(['token' => "demo-{$slug}-targeted"]);
        $base->expired()->create(['token' => "demo-{$slug}-expired"]);
        $base->revoked()->create(['token' => "demo-{$slug}-revoked"]);
        $base->exhausted()->create(['token' => "demo-{$slug}-exhausted"]);
    }

    /**
     * @param  Collection<int, Actor>  $members
     * @param  Collection<int, GroupAgreementVersion>  $activeVersions
     */
    private function seedAdmissionEdgeCases(Group $group, Actor $owner, Collection $members, Collection $activeVersions, string $slug): void
    {
        $statuses = match ($slug) {
            'riverside' => ['draft', 'submitted', 'under_review'],
            'al-noor' => ['clarification_required', 'approved', 'rejected'],
            default => ['cancelled', 'finalized'],
        };

        foreach ($statuses as $status) {
            /** @var Actor $candidate */
            $candidate = $status === 'finalized'
                ? $members->last()
                : $this->createActor("{$slug}_candidate_{$status}", "{$status}.{$slug}@example.test");

            $invitation = GroupInvitation::factory()
                ->for($group)
                ->for($owner, 'inviter')
                ->targeted($candidate->user->email)
                ->create(['token' => "demo-{$slug}-admission-{$status}", 'uses_count' => 1]);

            GroupInvitationAcceptance::factory()
                ->for($invitation, 'invitation')
                ->for($candidate, 'acceptedBy')
                ->create();

            $admission = Admission::factory()
                ->fromInvitation($invitation)
                ->for($candidate, 'candidate')
                ->state($this->admissionState($status))
                ->create();

            AdmissionEvent::factory()->for($admission)->for($candidate)->create([
                'event' => 'admission.created_from_invitation',
                'note' => 'I would like to contribute to this construction project and can provide the requested role evidence.',
                'metadata' => ['invitation_id' => $invitation->id],
                'created_at' => now()->subDays(4),
            ]);

            if ($status !== 'draft') {
                AdmissionEvent::factory()->for($admission)->for($candidate)->create([
                    'event' => 'admission.submitted',
                    'note' => 'Availability, qualifications, and proposed responsibilities submitted for review.',
                    'created_at' => now()->subDays(3),
                ]);
            }

            if (! in_array($status, ['draft', 'submitted'], true)) {
                AdmissionEvent::factory()->for($admission)->for($owner)->create([
                    'event' => "admission.{$status}",
                    'note' => $admission->decision_note,
                    'created_at' => now()->subDay(),
                ]);
            }

            if ($status === 'approved') {
                /** @var GroupAgreementVersion $acceptedVersion */
                $acceptedVersion = $activeVersions->first();
                AgreementAcceptance::factory()->forEvidence($admission, $acceptedVersion)->create();
            }

            if ($status === 'finalized') {
                /** @var GroupMembership $membership */
                $membership = GroupMembership::query()
                    ->where('group_id', $group->id)
                    ->where('actor_id', $candidate->id)
                    ->firstOrFail();

                $activeVersions->each(function (GroupAgreementVersion $version) use ($admission, $membership): void {
                    AgreementAcceptance::factory()->forEvidence($admission, $version)->create();
                    MembershipAgreementAcceptance::factory()->forEvidence($membership, $version)->create();
                });
            }
        }
    }

    /** @return array<string, mixed> */
    private function admissionState(string $status): array
    {
        return match ($status) {
            'submitted' => ['status' => $status, 'submitted_at' => now()->subDays(3)],
            'under_review' => ['status' => $status, 'submitted_at' => now()->subDays(3)],
            'clarification_required' => ['status' => $status, 'submitted_at' => now()->subDays(3), 'decision_note' => 'Please add your trade license and availability date.'],
            'approved' => ['status' => $status, 'submitted_at' => now()->subDays(3), 'approved_at' => now()->subDay(), 'decision_note' => 'Approved subject to every required agreement.'],
            'finalized' => ['status' => $status, 'submitted_at' => now()->subDays(4), 'approved_at' => now()->subDays(2), 'finalized_at' => now()->subDay(), 'decision_note' => 'Requirements satisfied and membership finalized.'],
            'rejected' => ['status' => $status, 'submitted_at' => now()->subDays(3), 'rejected_at' => now()->subDay(), 'decision_note' => 'Required qualification evidence was not provided.'],
            'cancelled' => ['status' => $status, 'submitted_at' => now()->subDays(3), 'cancelled_at' => now()->subDay(), 'decision_note' => 'Candidate withdrew before review.'],
            default => ['status' => 'draft'],
        };
    }

    /**
     * @param  Collection<int, Actor>  $workers
     * @param  Collection<string, Role>  $roles
     */
    private function seedRoleChangeRequests(Group $group, Actor $owner, Collection $workers, Collection $roles, GroupRoleProvisioner $roleProvisioner): void
    {
        foreach (['pending', 'approved', 'rejected'] as $index => $status) {
            /** @var Actor $worker */
            $worker = $workers[$index];
            /** @var GroupMembership $membership */
            $membership = GroupMembership::query()
                ->where('group_id', $group->id)
                ->where('actor_id', $worker->id)
                ->firstOrFail();
            /** @var Role $requestedRole */
            $requestedRole = $roles->values()[($index + 3) % $roles->count()];

            GroupRoleChangeRequest::factory()->create([
                'group_id' => $group->id,
                'membership_id' => $membership->id,
                'requested_role_id' => $requestedRole->id,
                'status' => $status,
                'reviewed_by_actor_id' => $status === 'pending' ? null : $owner->id,
                'reviewed_at' => $status === 'pending' ? null : now()->subDay(),
                'review_locked_at' => $status === 'pending' ? null : now()->subDay(),
            ]);

            if ($status === 'approved') {
                $roleProvisioner->assign($worker, $group, $requestedRole);
            }
        }
    }

    /**
     * @param  Collection<int, Actor>  $members
     * @param  Collection<int, GroupAgreementVersion>  $activeVersions
     */
    private function seedMembershipAgreementAcceptances(Group $group, Collection $members, Collection $activeVersions): void
    {
        $members->take(3)->each(function (Actor $actor) use ($group, $activeVersions): void {
            /** @var GroupMembership $membership */
            $membership = GroupMembership::query()
                ->where('group_id', $group->id)
                ->where('actor_id', $actor->id)
                ->firstOrFail();

            $activeVersions->each(
                fn (GroupAgreementVersion $version) => MembershipAgreementAcceptance::factory()->forEvidence($membership, $version)->create(),
            );
        });
    }

    private function seedAccountEdgeCases(): void
    {
        foreach (['suspended' => 'suspended_demo', 'closed' => 'closed_demo'] as $status => $username) {
            $email = "{$status}@example.test";
            $user = User::query()->where('email', $email)->first()
                ?? User::factory()->{$status}()->create(compact('email', 'username'));
            Actor::query()->firstOrCreate(['user_id' => $user->id]);
        }

        if (Actor::query()->whereNull('user_id')->doesntExist()) {
            Actor::factory()->withoutUser()->create();
        }

        $unverified = User::query()->where('email', 'unverified@example.test')->first()
            ?? User::factory()->unverified()->create([
                'username' => 'unverified_demo',
                'email' => 'unverified@example.test',
            ]);
        Actor::query()->firstOrCreate(['user_id' => $unverified->id]);
    }

    private function createActor(string $username, string $email): Actor
    {
        $user = User::factory()->create(compact('username', 'email'));

        return Actor::factory()->for($user)->create();
    }
}
