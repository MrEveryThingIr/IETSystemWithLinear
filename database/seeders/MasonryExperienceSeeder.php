<?php

namespace Database\Seeders;

use App\Actions\Deals\ManageDealMoney;
use App\Actions\Deals\ManageDealWork;
use App\Actions\Deals\ProposeDealTerms;
use App\Actions\Fulfillments\ReviewFulfillment;
use App\Actions\Fulfillments\SubmitFulfillment;
use App\Actions\Profile\CreateActorProfileIntent;
use App\Actions\Profile\EnsureActorProfile;
use App\Actions\Proposals\RespondToProposal;
use App\Actions\Relationships\CreateRelationship;
use App\Actions\Relationships\RespondToRelationship;
use App\FulfillmentReviewDecision;
use App\Models\Bookkeeping\Transaction;
use App\Models\Proposal;
use App\Models\Relationship;
use App\Models\User;
use App\ProfileIntentExchangePreference;
use App\ProfileIntentKind;
use App\ProfileIntentScheduleKind;
use App\ProfileItemVisibility;
use App\ProposalDecisionKind;
use App\Services\Surfaces\FeatureSurfaceGrantService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Explicit, LOCAL-ONLY scenario for inspecting the actual four-tab Deal experience.
 *
 * Run only with: IET_MASONRY_DEMO_ALLOW=1 IET_MASONRY_DEMO_PASSWORD=<long random secret>
 * php artisan db:seed --class=Database\\Seeders\\MasonryExperienceSeeder
 *
 * Never included in DatabaseSeeder. Never resets, deletes or overwrites existing data.
 * Dates are historical simulation dates; generated records are DEMO, not true evidence.
 */
class MasonryExperienceSeeder extends Seeder
{
    private const KEY = 'iet-masonry-two-week-acceptance-v1';

    public function run(): void
    {
        if (! app()->environment('local')
            || (string) env('IET_MASONRY_DEMO_ALLOW', '') !== '1'
            || ! in_array((string) config('database.default'), ['mysql', 'mariadb', 'sqlite'], true)) {
            throw new RuntimeException('Masonry demo requires local environment and explicit IET_MASONRY_DEMO_ALLOW=1.');
        }

        $password = (string) env('IET_MASONRY_DEMO_PASSWORD', '');
        if (mb_strlen($password) < 16) {
            throw new RuntimeException('Set IET_MASONRY_DEMO_PASSWORD to a unique secret at least 16 characters long.');
        }

        $title = 'آزمایشی · بنایی ساختمان پنج‌طبقه (۱۴ روز)';
        if (Relationship::query()->where('title', $title)->exists()) {
            throw new RuntimeException('This demo Deal already exists. Continue using it instead of creating duplicate money records.');
        }
        foreach (['iet-demo-employer', 'iet-demo-mason'] as $username) {
            if (User::query()->where('username', $username)->exists()) {
                throw new RuntimeException("Reserved demo username already exists: {$username}. No records created.");
            }
        }

        $actualNow = CarbonImmutable::now();
        $start = $actualNow->setTimezone('Asia/Tehran')->startOfDay()->subDays(14);
        $previousClock = Carbon::getTestNow();
        try {
            // Entire demo is all-or-nothing. Domain actions still execute their normal policies and audit writes.
            DB::transaction(function () use ($title, $password, $start): void {
                Carbon::setTestNow($start->setTime(9, 0));
                $employer = $this->user('iet-demo-employer', 'iet-demo-employer@example.invalid', $password);
                $mason = $this->user('iet-demo-mason', 'iet-demo-mason@example.invalid', $password);

                $profile = app(EnsureActorProfile::class)->execute($employer);
                $intent = app(CreateActorProfileIntent::class)->execute(
                    $employer, $profile, ProfileIntentKind::Need, 'ساختمان و بنایی',
                    [
                        'title' => 'نیاز به بنا برای دیوارچینی ساختمان پنج‌طبقه',
                        'description' => '۵ طبقه، حدود ۱۳۰ مترمربع دیوار در هر طبقه. بازدید و متره نهایی مبنای تسویه است.',
                        'subject_kind' => 'service',
                        'arrangement_kind' => 'service',
                        'exchange_preference' => ProfileIntentExchangePreference::OpenHybrid->value,
                        'cash_min' => '300000',
                        'cash_max' => '300000',
                        'currency_code' => 'IRT',
                        'cash_basis' => 'square_metre',
                        'quantity' => '650',
                        'unit' => 'square_metre',
                        'schedule_kind' => ProfileIntentScheduleKind::Ongoing->value,
                        'timezone' => 'Asia/Tehran',
                        'visibility' => ProfileItemVisibility::Public->value,
                    ],
                );
                $intent->forceFill(['metadata' => [...($intent->metadata ?? []), 'demo_scenario' => self::KEY]])->save();

                $deal = app(CreateRelationship::class)->execute(
                    $employer, $intent->concept, 'employer',
                    [['actor' => $mason->actor, 'role' => 'contractor']],
                    originatingIntent: $intent, title: $title,
                );
                app(RespondToRelationship::class)->execute($deal, $mason, true);
                $deal = $deal->fresh();

                $terms = [
                    'schema' => 1,
                    'title' => 'توافق بنایی: چهار روز دستمزد روزانه و ادامه دیوارچینی متری',
                    'summary' => 'چهار روز اول دستمزد روزانه و سپس دیوارچینی بر اساس مترمربع واقعی تأییدشده.',
                    'rates' => [
                        ['key' => 'daily', 'label' => 'دستمزد روزانه (۴ روز)', 'unit' => 'day', 'rate' => '1800000', 'estimate' => '4'],
                        ['key' => 'walls', 'label' => 'دیوارچینی با سفال ۱۰ و ۱۵', 'unit' => 'square_metre', 'rate' => '300000', 'estimate' => '650'],
                    ],
                    'payment' => ['mode' => 'after_confirmed_delivery'],
                    'completion' => ['confirmed_by' => 'beneficiary'],
                    'notes' => 'مصالح و مسئولیت‌ها مطابق توافق طرفین؛ مقادیر تخمینی بدهی نیستند.',
                ];
                app(ProposeDealTerms::class)->execute($deal, $employer, $terms);
                $proposal = Proposal::query()->where('relationship_id', $deal->id)->latest('id')->firstOrFail();
                app(RespondToProposal::class)->execute($proposal, $mason, ProposalDecisionKind::Accepted, null, 1);

                $work = app(ManageDealWork::class);
                $money = app(ManageDealMoney::class);
                // 4 daily-wage days, followed by 7 masonry workdays across the same 14-day window.
                // A pending inspection and a partial acceptance give both accounts meaningful next actions.
                $days = [
                    ['offset' => 1, 'rate' => 'daily', 'qty' => '1', 'accepted' => '1', 'note' => 'روز اول؛ آماده‌سازی، بررسی محل و ابزار.'],
                    ['offset' => 2, 'rate' => 'daily', 'qty' => '1', 'accepted' => '1', 'note' => 'روز دوم؛ آماده‌سازی مسیر و تقسیم کار.'],
                    ['offset' => 3, 'rate' => 'daily', 'qty' => '1', 'accepted' => '1', 'note' => 'روز سوم؛ آماده‌سازی مصالح.'],
                    ['offset' => 4, 'rate' => 'daily', 'qty' => '1', 'accepted' => '1', 'note' => 'روز چهارم؛ تحویل آماده‌سازی و تغییر مبنای دستمزد.'],
                    ['offset' => 6, 'rate' => 'walls', 'qty' => '14', 'accepted' => '14', 'note' => 'دیوارچینی طبقه اول.'],
                    ['offset' => 7, 'rate' => 'walls', 'qty' => '17', 'accepted' => '15', 'note' => 'دیوارچینی، دو متر نیازمند اصلاح.'],
                    ['offset' => 8, 'rate' => 'walls', 'qty' => '11', 'accepted' => '11', 'note' => 'تکمیل دیوارهای جداکننده.'],
                    ['offset' => 9, 'rate' => 'walls', 'qty' => '16', 'accepted' => '16', 'note' => 'شروع طبقه دوم.'],
                    ['offset' => 10, 'rate' => 'walls', 'qty' => '13', 'accepted' => '13', 'note' => 'دیوارچینی طبقه دوم.'],
                    ['offset' => 11, 'rate' => 'walls', 'qty' => '12', 'accepted' => '12', 'note' => 'نیاز به سفارش سفال بیشتر برای هفته بعد.'],
                    ['offset' => 13, 'rate' => 'walls', 'qty' => '9', 'accepted' => null, 'note' => 'گزارش امروز؛ منتظر بازدید و تأیید کارفرما.'],
                ];

                // Replay in order: policies, balances and history share the same clock.
                for ($offset = 1; $offset <= 13; $offset++) {
                    if (in_array($offset, [5, 12], true)) {
                        Carbon::setTestNow($start->addDays($offset)->setTime(19, 0));
                        if ($offset === 5) {
                            $payment = $money->payOnAccount($deal->fresh(), $employer, '2500000', 'masonry-demo-advance-v1', note: 'علی‌الحساب برای شروع کار');
                        } else {
                            $ask = $money->askOnAccount($deal->fresh(), $mason, '4000000', 'درخواست بخشی از طلب، باقی پس از متره');
                            $payment = $money->payOnAccount($deal->fresh(), $employer, '3000000', 'masonry-demo-partial-v1', note: 'قسط دوم برای دیوارچینی', purpose: 'instalment', askId: $ask['id']);
                        }
                        $money->approve($deal->fresh(), $mason, $payment->uuid);

                        continue;
                    }
                    $row = collect($days)->firstWhere('offset', $offset);
                    if ($row === null) {
                        continue;
                    }
                    $day = $start->addDays($row['offset'])->setTime(17, 30);
                    Carbon::setTestNow($day);
                    $duty = $work->forRate($deal->fresh(), $mason, $row['rate']);
                    $fulfillment = app(SubmitFulfillment::class)->execute(
                        $duty, $mason, $row['qty'],
                        actualStartAt: $day->setTime(8, 0),
                        actualEndAt: $day->setTime(17, 0),
                        notes: $row['note'],
                    );
                    if ($row['accepted'] !== null) {
                        app(ReviewFulfillment::class)->execute(
                            $fulfillment, $employer, FulfillmentReviewDecision::Accepted,
                            acceptedQuantity: $row['accepted'],
                        );
                        $money->recordAccepted($deal->fresh(), $employer, $fulfillment->fresh());
                        // Recipient of the proposed obligation explicitly acknowledges the entry.
                        $entry = Transaction::query()
                            ->where('source_uuid', $fulfillment->uuid)
                            ->where('type', 'obligation')->latest('id')->firstOrFail();
                        $money->approve($deal->fresh(), $mason, $entry->uuid);
                    }
                }

                Carbon::setTestNow($start->addDays(14)->setTime(9, 0));
                $this->command?->info('Masonry demo created. Log in as iet-demo-employer or iet-demo-mason with IET_MASONRY_DEMO_PASSWORD.');
                $this->command?->info('Deal: /relationships/'.$deal->uuid.'?tab=work');
                $this->command?->info('Open the Money tab and examine the distinct payer/payee perspectives, accepted days and pending inspection.');
            }, 1);
        } finally {
            Carbon::setTestNow($previousClock);
        }
    }

    private function user(string $username, string $email, string $password): User
    {
        $user = User::factory()->create([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'status' => 'active',
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
        ]);
        $user->actor()->firstOrCreate([], ['status' => 'active']);

        app(FeatureSurfaceGrantService::class)->sync($user, ['market', 'deals', 'messages'], null);

        return $user->fresh('actor');
    }
}
