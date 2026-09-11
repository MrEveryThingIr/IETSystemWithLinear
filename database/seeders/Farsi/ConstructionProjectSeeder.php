<?php

namespace Database\Seeders\Farsi;

use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\ManageGroupAgreement;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupAgreementVersion;
use App\Models\GroupMembership;
use App\Models\Responsibility;
use App\Models\Story;
use App\Models\User;
use Database\Factories\Farsi\ActorFactory;
use Database\Factories\Farsi\AdmissionEventFactory;
use Database\Factories\Farsi\AdmissionFactory;
use Database\Factories\Farsi\AgreementAcceptanceFactory;
use Database\Factories\Farsi\GroupInvitationAcceptanceFactory;
use Database\Factories\Farsi\GroupInvitationFactory;
use Database\Factories\Farsi\GroupMembershipFactory;
use Database\Factories\Farsi\GroupRoleChangeRequestFactory;
use Database\Factories\Farsi\MembershipAgreementAcceptanceFactory;
use Database\Factories\Farsi\StoryFactory;
use Database\Factories\Farsi\StoryRoleFactory;
use Database\Factories\Farsi\UserFactory;
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
            'name' => 'پروژه ساخت مجتمع مسکونی کنار رود',
            'description' => 'پیگیری پیشرفت سازه، معماری، برق، لوله‌کشی، تدارکات، ایمنی، هزینه و تحویل برای یک ساختمان مسکونی دوازده‌واحدی.',
        ],
        [
            'slug' => 'al-noor',
            'name' => 'مرکز اجتماعی و فرهنگی النور',
            'description' => 'هماهنگی مالکان، پیمانکاران، اصناف، تأمین‌کنندگان، بازرسی‌ها، آماده‌سازی پرداخت‌ها و پیشرفت روزانه کارگاه برای یک مرکز اجتماعی.',
        ],
        [
            'slug' => 'greenline',
            'name' => 'بازسازی کاربری مختلط گرین‌لاین',
            'description' => 'همگام‌سازی یک بازسازی فعال در طبقات تجاری و مسکونی با پیگیری تردد، ایمنی، تدارکات، کیفیت، هزینه و تکمیل مرحله‌ای.',
        ],
    ];

    /** @var array<string, list<string>> */
    private const PROJECT_ROLES = [
        'مدیر پروژه' => ['manage_group', 'manage_members', 'manage_invitations', 'approve_role_changes', 'participate'],
        'پیمانکار اصلی' => ['manage_members', 'manage_invitations', 'participate'],
        'مهندس عمران' => ['participate'],
        'معمار' => ['participate'],
        'پیمانکار برق' => ['participate'],
        'پیمانکار لوله‌کشی' => ['participate'],
        'ناظر کارگاه' => ['participate'],
        'مسئول ایمنی' => ['participate'],
        'مترور' => ['participate'],
        'تأمین‌کننده مصالح ساختمانی' => ['participate'],
        'کارگر ماهر' => ['participate'],
        'کارگر ساده' => ['participate'],
    ];

    public function run(): void
    {
        fake()->seed(20260911);
        fake('fa_IR')->seed(20260911);
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
            fn (int $number): Actor => $this->createActor("partner{$number}.{$project['slug']}@example.test"),
        );

        /** @var Actor $creator */
        $creator = $owners->first();
        $group = $createGroup->execute($creator, $project['name'], $project['description']);
        $builtInRoles = $roleProvisioner->provision($group);

        $owners->skip(1)->each(function (Actor $owner) use ($group, $roleProvisioner, $builtInRoles): void {
            GroupMembershipFactory::new()->for($group)->for($owner)->create();
            $roleProvisioner->assign($owner, $group, $builtInRoles['owner']);
        });

        $projectRoles = collect(self::PROJECT_ROLES)->mapWithKeys(
            fn (array $permissions, string $name): array => [$name => $roleProvisioner->createRole($group, $name, $permissions)],
        );

        $workers = collect(range(1, $memberCount - 3))->map(function (int $number) use ($project, $group, $projectRoles, $roleProvisioner): Actor {
            $actor = $this->createActor("member{$number}.{$project['slug']}@example.test");
            GroupMembershipFactory::new()->for($group)->for($actor)->create();
            /** @var Role $role */
            $role = $projectRoles->values()[($number - 1) % $projectRoles->count()];
            $roleProvisioner->assign($actor, $group, $role);

            return $actor;
        });

        $members = $owners->concat($workers)->values();
        $formerUser = UserFactory::new()->suspended()->create([
            'username' => $this->persianName(),
            'email' => "former.{$project['slug']}@example.test",
        ]);
        $formerActor = ActorFactory::new()->for($formerUser)->create();
        GroupMembershipFactory::new()->for($group)->for($formerActor)->removed()->create();

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
            'توافق‌نامه مشارکت و گزارش‌دهی پروژه',
            true,
            'اعضا موظف‌اند پیشرفت مصالح، تأخیرها، حوادث ایمنی، نتایج بازرسی و موانع را در گروه گزارش کنند. تعیین نقش‌ها صرفاً اختیار هماهنگی می‌دهد و جایگزین قراردادهای امضاشده کاری، تأمین یا مشارکت نمی‌شود.',
        );
        /** @var GroupAgreementVersion $participationVersion */
        $participationVersion = $participation->versions()->firstOrFail();
        $manager->propose($participationVersion, $owner);
        $manager->approve($participationVersion, $owner);
        $manager->activate($participationVersion, $owner);

        $safety = $manager->create(
            $group,
            $owner,
            'توافق‌نامه ایمنی و تردد در کارگاه',
            true,
            'هر مشارکت‌کننده باید آموزش‌های ورود به کارگاه، تجهیزات حفاظت فردی، کنترل تردد، گزارش حوادث و الزامات توقف کار را رعایت کند. کارهای پرخطر پیش از شروع به تأیید ناظر مسئول نیاز دارند.',
        );
        /** @var GroupAgreementVersion $safetyVersion */
        $safetyVersion = $safety->versions()->firstOrFail();
        $manager->propose($safetyVersion, $owner);
        $manager->approve($safetyVersion, $owner);
        $manager->activate($safetyVersion, $owner);

        $manager->create(
            $group,
            $owner,
            'یادداشت‌های برنامه‌ریزی پرداخت و تسویه',
            false,
            'صرفاً یک جایگاه برنامه‌ریزی است: روش پرداخت، واحد پول، مبنای صورتحساب، تأیید متره، حسن انجام کار، مالیات، سررسید، روند حل اختلاف و تسویه نهایی همگی باید تا پیاده‌سازی حوزه‌های آینده قرارداد و تعهدات و امضای طرفین ذی‌ربط «تعیین نشده» باقی بمانند.',
        );

        $revision = $manager->revise(
            $participation,
            $owner,
            'اعضا باید هر هفته شواهد پیشرفت، موانع، تصمیم‌ها، نتایج بازرسی و حوادث ایمنی را منتشر کنند. مطالبات مالی به شواهد متره و تأیید مالک نیاز دارند، اما تعهدات اجرایی در یک قرارداد امضاشده جداگانه باقی می‌ماند.',
            'آماده‌سازی توافق‌نامه گزارش‌دهی برای گردش‌کار آینده فضاها و محتوا.',
            true,
        );
        $manager->propose($revision, $owner);

        if ($slug === 'al-noor') {
            $manager->approve($revision, $owner);
            $manager->schedule($revision, $owner, now()->addMonth());
        } elseif ($slug === 'greenline') {
            $manager->reject($revision, $owner, 'پیش از بررسی مجدد این نسخه، مشخص کنید چه کسی شواهد متره را تأیید می‌کند.');
        }

        return collect([$participationVersion->refresh(), $safetyVersion->refresh()]);
    }

    /** @param Collection<int, Actor> $members */
    private function seedStories(Group $group, Actor $owner, Collection $members): void
    {
        $roleDescriptions = [
            'مدیر پروژه — هماهنگ‌کننده محدوده، زمان‌بندی، تصمیم‌ها، ریسک‌ها و گزارش به مالکان.',
            'پیمانکار اصلی — برنامه‌ریزی روش‌های اجرا، پیمانکاران جزء، نیروی کار، ترتیب اجرا و تحویل کارگاه.',
            'مهندس عمران — کنترل کارهای سازه‌ای، انطباق فنی، متره و شواهد بازرسی.',
            'معمار — کنترل نقشه‌ها، نازک‌کاری، تصمیم‌های فضایی، مصالح ارائه‌شده و رفع ابهامات طراحی.',
            'پیمانکار برق — نصب و آزمایش برق، روشنایی، ارت، سیستم‌های فشار ضعیف و ایمنی برق.',
            'پیمانکار لوله‌کشی — نصب و آزمایش سیستم‌های آب، فاضلاب، بهداشت، پمپاژ و تجهیزات.',
            'ناظر کارگاه — هماهنگی روزانه جبهه‌های کار، حضور و غیاب، مصالح، تجهیزات و موانع.',
            'مسئول ایمنی — مدیریت آموزش ورود، مجوزها، بازرسی‌ها، حوادث، اقدامات اصلاحی و تشدید توقف کار.',
            'مترور — اعتبارسنجی مقادیر، تغییرات، صورت‌وضعیت‌ها، حسن انجام کار و شواهد تسویه.',
            'تأمین‌کننده مصالح ساختمانی — تأیید مشخصات، استعلام قیمت، زمان تحویل، شواهد تحویل و جایگزینی اقلام معیوب.',
            'کارگر ماهر — اجرای کارهای تخصصی طبق نقشه و ثبت مقادیر، کنترل کیفیت و موانع.',
            'کارگر ساده — پشتیبانی از جابه‌جایی، آماده‌سازی، نظافت، تردد و کارهای اجرایی تحت نظارت.',
        ];

        $stories = collect([
            'منشور پروژه و حکمرانی شرکای مالک' => 'سه شریک مالک نظارت بر پروژه را تقسیم می‌کنند. شریک اول مالک اصلی است. تصمیم‌های محفوظ: تغییر محدوده، مبنای بودجه، انتصاب پیمانکار، تغییرات عمده، تعلیق و تحویل نهایی. آستانه‌های تصمیم‌گیری، سهم مالکیت، اختیار امضا و روند حل بن‌بست «[تعیین نشده — قرارداد امضاشده آینده]» است.',
            'ماتریس نقش‌ها و مسئولیت‌ها' => implode("\n", $roleDescriptions),
            'جایگاه‌های پرداخت و تسویه' => "این سند هیچ تعهد پرداخت اجرایی ایجاد نمی‌کند.\n\nمرجع قرارداد: [تعیین نشده]\nپرداخت‌گیرنده و پرداخت‌کننده: [تعیین نشده]\nواحد پول: [تعیین نشده]\nروش/حساب پرداخت: [تعیین نشده]\nمبنای صورتحساب (قیمت مقطوع، نقطه عطف، متره، روزمزد): [تعیین نشده]\nمرجع متره و تأیید: [تعیین نشده]\nصورتحساب و مدارک پشتیبان: [تعیین نشده]\nمالیات و کسورات: [تعیین نشده]\nدرصد/آزادسازی حسن انجام کار: [تعیین نشده]\nسررسید و جریمه تأخیر: [تعیین نشده]\nتأیید تغییرات: [تعیین نشده]\nروند حل اختلاف و تسویه نهایی: [تعیین نشده]\nوضعیت فعلی تسویه: قرارداد منعقد نشده است.",
            'پروتکل گزارش‌دهی پیشرفت' => "روزانه: نیروی کار، مقادیر اجراشده، تحویل‌ها، بازرسی‌ها، حوادث، تأخیرها، موانع، ارجاع به عکس/شواهد و برنامه روز بعد.\nهفتگی: نقاط عطف برنامه‌ریزی‌شده در برابر واقعی، مسیر بحرانی، مسائل کیفی، اقدامات ایمنی، ریسک‌های تدارکات، درخواست‌های تغییر، پیش‌بینی هزینه و تصمیم‌های موردنیاز از مالکان.",
            'نمونه گزارش روزانه کارگاه' => "تاریخ: [نمونه]\nوضعیت هوا/تردد: عادی\nکار انجام‌شده: مسیردهی لوله برق طبقه همکف و آماده‌سازی بازرسی لوله‌کشی مرحله اول\nنیروی کار: [تعیین نشده توسط صنف]\nمصالح تحویل‌شده: [ارجاع تحویل تعیین نشده]\nکنترل کیفیت/ایمنی: [شواهد تعیین نشده]\nموانع: نقشه هماهنگی سقف نیاز به تأیید معمار دارد\nمسئول تصمیم: مدیر پروژه\nاقدام بعدی: انتشار نقشه هماهنگ و درخواست بازرسی.",
        ])->map(
            fn (string $body, string $title): Story => StoryFactory::new()->for($group)->for($owner, 'creator')->create(compact('title', 'body')),
        );

        $stories->values()->each(function (Story $story, int $index) use ($members): void {
            $contributors = $members->slice($index % $members->count(), 3)->values();
            if ($contributors->count() < 3) {
                $contributors = $contributors->concat($members->take(3 - $contributors->count()));
            }

            foreach ([Responsibility::Author, Responsibility::Editor, Responsibility::Contributor] as $position => $responsibility) {
                StoryRoleFactory::new()->for($story)->for($contributors[$position])->create(compact('responsibility'));
            }
        });
    }

    private function seedInvitationEdgeCases(Group $group, Actor $owner, string $slug): void
    {
        $base = GroupInvitationFactory::new()->for($group)->for($owner, 'inviter');

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
                : $this->createActor("{$status}.{$slug}@example.test");

            $invitation = GroupInvitationFactory::new()
                ->for($group)
                ->for($owner, 'inviter')
                ->targeted($candidate->user->email)
                ->create(['token' => "demo-{$slug}-admission-{$status}", 'uses_count' => 1]);

            GroupInvitationAcceptanceFactory::new()
                ->for($invitation, 'invitation')
                ->for($candidate, 'acceptedBy')
                ->create();

            $admission = AdmissionFactory::new()
                ->fromInvitation($invitation)
                ->for($candidate, 'candidate')
                ->state($this->admissionState($status))
                ->create();

            AdmissionEventFactory::new()->for($admission)->for($candidate)->create([
                'event' => 'admission.created_from_invitation',
                'note' => 'مایلم در این پروژه ساختمانی مشارکت کنم و می‌توانم مدارک نقش درخواستی را ارائه دهم.',
                'metadata' => ['invitation_id' => $invitation->id],
                'created_at' => now()->subDays(4),
            ]);

            if ($status !== 'draft') {
                AdmissionEventFactory::new()->for($admission)->for($candidate)->create([
                    'event' => 'admission.submitted',
                    'note' => 'اطلاعات آمادگی، صلاحیت‌ها و مسئولیت‌های پیشنهادی برای بررسی ارسال شد.',
                    'created_at' => now()->subDays(3),
                ]);
            }

            if (! in_array($status, ['draft', 'submitted'], true)) {
                AdmissionEventFactory::new()->for($admission)->for($owner)->create([
                    'event' => "admission.{$status}",
                    'note' => $admission->decision_note,
                    'created_at' => now()->subDay(),
                ]);
            }

            if ($status === 'approved') {
                /** @var GroupAgreementVersion $acceptedVersion */
                $acceptedVersion = $activeVersions->first();
                AgreementAcceptanceFactory::new()->forEvidence($admission, $acceptedVersion)->create();
            }

            if ($status === 'finalized') {
                /** @var GroupMembership $membership */
                $membership = GroupMembership::query()
                    ->where('group_id', $group->id)
                    ->where('actor_id', $candidate->id)
                    ->firstOrFail();

                $activeVersions->each(function (GroupAgreementVersion $version) use ($admission, $membership): void {
                    AgreementAcceptanceFactory::new()->forEvidence($admission, $version)->create();
                    MembershipAgreementAcceptanceFactory::new()->forEvidence($membership, $version)->create();
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
            'clarification_required' => ['status' => $status, 'submitted_at' => now()->subDays(3), 'decision_note' => 'لطفاً پروانه کسب و تاریخ شروع همکاری خود را اضافه کنید.'],
            'approved' => ['status' => $status, 'submitted_at' => now()->subDays(3), 'approved_at' => now()->subDay(), 'decision_note' => 'مشروط به پذیرش همه توافق‌نامه‌های لازم، تأیید شد.'],
            'finalized' => ['status' => $status, 'submitted_at' => now()->subDays(4), 'approved_at' => now()->subDays(2), 'finalized_at' => now()->subDay(), 'decision_note' => 'الزامات تکمیل و عضویت نهایی شد.'],
            'rejected' => ['status' => $status, 'submitted_at' => now()->subDays(3), 'rejected_at' => now()->subDay(), 'decision_note' => 'مدارک صلاحیت لازم ارائه نشد.'],
            'cancelled' => ['status' => $status, 'submitted_at' => now()->subDays(3), 'cancelled_at' => now()->subDay(), 'decision_note' => 'داوطلب پیش از بررسی انصراف داد.'],
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

            GroupRoleChangeRequestFactory::new()->create([
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
                fn (GroupAgreementVersion $version) => MembershipAgreementAcceptanceFactory::new()->forEvidence($membership, $version)->create(),
            );
        });
    }

    private function seedAccountEdgeCases(): void
    {
        foreach (['suspended' => 'حساب معلق آزمایشی', 'closed' => 'حساب بسته‌شده آزمایشی'] as $status => $username) {
            $email = "{$status}@example.test";
            $user = User::query()->where('email', $email)->first()
                ?? UserFactory::new()->{$status}()->create(compact('email', 'username'));
            Actor::query()->firstOrCreate(['user_id' => $user->id]);
        }

        if (Actor::query()->whereNull('user_id')->doesntExist()) {
            ActorFactory::new()->withoutUser()->create();
        }

        $unverified = User::query()->where('email', 'unverified@example.test')->first()
            ?? UserFactory::new()->unverified()->create([
                'username' => 'حساب تأییدنشده آزمایشی',
                'email' => 'unverified@example.test',
            ]);
        Actor::query()->firstOrCreate(['user_id' => $unverified->id]);
    }

    private function createActor(string $email): Actor
    {
        $user = UserFactory::new()->create([
            'username' => $this->persianName(),
            'email' => $email,
        ]);

        return ActorFactory::new()->for($user)->create();
    }

    private function persianName(): string
    {
        return fake('fa_IR')->unique()->firstName().' '.fake('fa_IR')->lastName();
    }
}
