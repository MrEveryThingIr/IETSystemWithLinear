<?php

namespace Tests\Feature;

use App\Actions\Bookkeeping\AddLedgerParty;
use App\Actions\Bookkeeping\CreateLedger;
use App\Actions\Bookkeeping\DecideOnEntry;
use App\Actions\Bookkeeping\EnsurePersonalLedger;
use App\Actions\Bookkeeping\RecordPersonalEntry;
use App\Actions\Commitments\BindExistingPlan;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Contracts\AcceptContractVersion;
use App\Actions\Contracts\CreateDirectContract;
use App\Actions\Fulfillments\ReviewFulfillment;
use App\Actions\Fulfillments\SubmitFulfillment;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Actions\Planner\SavePlanTemplate;
use App\Actions\Proposals\CreateProposal;
use App\Actions\Proposals\RespondToProposal;
use App\Actions\Work\ProposeWorkBookkeepingEntry;
use App\Enums\Bookkeeping\EntryOrigin;
use App\Enums\Bookkeeping\LedgerKind;
use App\Enums\Bookkeeping\TransactionStatus;
use App\Enums\Bookkeeping\TransactionType;
use App\FulfillmentReviewDecision;
use App\Livewire\Bookkeeping\BookShow;
use App\Livewire\Commitments\Show as CommitmentShow;
use App\Livewire\Contracts\Create;
use App\Models\Actor;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\Transaction;
use App\Models\Commitment;
use App\Models\Contract;
use App\Models\ContractServiceTerm;
use App\Models\Fulfillment;
use App\Models\Plan;
use App\Models\PlanTemplate;
use App\PlanScheduleFrequency;
use App\PlanTimingMode;
use App\ProposalDecisionKind;
use App\Support\Parties\Party;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WorkPlannerBookkeepingTest extends TestCase
{
    use RefreshDatabase;

    private Actor $employer;

    private Actor $worker;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-01 07:00:00 UTC');
        $this->employer = Actor::factory()->create();
        $this->worker = Actor::factory()->create();
        foreach ([$this->employer, $this->worker] as $actor) {
            $actor->user->forceFill(['timezone' => 'UTC'])->save();
        }
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function serviceContract(string $currency = 'IRR', bool $autoPlan = false): Contract
    {
        $contract = app(CreateDirectContract::class)->execute(
            $this->employer->user,
            'Garden care',
            [['actor' => $this->worker, 'role' => 'worker']],
            'Weekly garden care.',
            CarbonImmutable::now(),
            'UTC',
            creatorRole: 'client',
            serviceTerms: [
                'employer_username' => $this->employer->user->username,
                'worker_username' => $this->worker->user->username,
                'service_title' => 'Garden care',
                'service_kind' => 'service',
                'total_quantity' => '4',
                'quantity_per_occurrence' => '1',
                'unit' => 'day',
                'unit_rate' => '1500000',
                'monetary_unit_code' => $currency,
                'settlement_cycle' => 'per_fulfillment',
                'payment_due_days' => 7,
                'plan_frequency' => 'once',
                'plan_starts_on' => '2026-10-02',
                'plan_start_time' => '08:00',
                'plan_duration_minutes' => 240,
                'auto_create_plan' => $autoPlan,
                'timezone' => 'UTC',
            ],
        );
        app(AcceptContractVersion::class)->execute($contract->versions()->sole(), $this->worker->user);

        return $contract->fresh();
    }

    private function acceptedWork(string $currency = 'IRR'): Fulfillment
    {
        $this->serviceContract($currency);
        $commitment = Commitment::query()->sole();
        $fulfillment = app(SubmitFulfillment::class)->execute($commitment, $this->worker->user, '2', notes: 'Two days done.');
        app(ReviewFulfillment::class)->execute($fulfillment, $this->employer->user, FulfillmentReviewDecision::Accepted);

        return $fulfillment->fresh();
    }

    private function sharedBook(): Ledger
    {
        $ledger = app(CreateLedger::class)->execute($this->employer->user, LedgerKind::Shared, 'Garden');
        app(AddLedgerParty::class)->execute($ledger, $this->employer->user, $this->worker->user->username, null);

        return $ledger->refresh();
    }

    public function test_accepted_work_is_proposed_once_with_provenance_and_counts_only_after_confirmation(): void
    {
        $fulfillment = $this->acceptedWork();
        $ledger = $this->sharedBook();
        $propose = app(ProposeWorkBookkeepingEntry::class);

        // 2 days × 1,500,000 rial = 3,000,000 rial = 300,000 toman.
        $this->assertSame(300000, $propose->amountFor($fulfillment));

        $entry = $propose->execute($this->worker->user, $fulfillment, $ledger);
        $this->assertSame(EntryOrigin::IetWorkflow, $entry->origin);
        $this->assertSame('fulfillment', $entry->source_type);
        $this->assertSame($fulfillment->uuid, $entry->source_uuid);
        $this->assertSame(1, $entry->source_version);
        $this->assertSame(TransactionStatus::Pending, $entry->status);
        $this->assertSame('2026-10-08', $entry->due_on->toDateString());

        // The other side proposing the same work gets the same entry, never a second one.
        $again = $propose->execute($this->employer->user, $fulfillment, $ledger);
        $this->assertSame($entry->id, $again->id);
        $this->assertSame(1, Transaction::query()->where('source_uuid', $fulfillment->uuid)->count());

        // It counts only when the employer confirms it in the book.
        app(DecideOnEntry::class)->approve($entry, $this->employer->user);
        $this->assertSame(TransactionStatus::Confirmed, $entry->fresh()->status);

        // Personal books stay untouched by IET.
        $this->assertSame(0, Transaction::query()->whereIn('type', ['income', 'expense', 'transfer'])->count());
    }

    public function test_rejected_proposal_can_be_proposed_again_and_outsiders_and_foreign_currency_are_refused(): void
    {
        $fulfillment = $this->acceptedWork();
        $ledger = $this->sharedBook();
        $propose = app(ProposeWorkBookkeepingEntry::class);
        $first = $propose->execute($this->worker->user, $fulfillment, $ledger);
        app(DecideOnEntry::class)->reject($first, $this->employer->user, 'Wrong amount');
        $second = $propose->execute($this->worker->user, $fulfillment, $ledger);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(TransactionStatus::Pending, $second->status);

        $outsider = Actor::factory()->create();
        try {
            $propose->execute($outsider->user, $fulfillment, $ledger);
            $this->fail('An outsider proposed an entry.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        // A book the worker is not in cannot receive the entry.
        $private = app(CreateLedger::class)->execute($this->employer->user, LedgerKind::Shared, 'Only me');
        $this->expectException(ValidationException::class);
        $propose->execute($this->employer->user, $fulfillment, $private);
    }

    public function test_no_toman_price_means_no_proposal(): void
    {
        $fulfillment = $this->acceptedWork('USD');
        $this->assertNull(app(ProposeWorkBookkeepingEntry::class)->amountFor($fulfillment));
        $this->expectException(ValidationException::class);
        app(ProposeWorkBookkeepingEntry::class)->execute($this->worker->user, $fulfillment, $this->sharedBook());
    }

    public function test_book_filters_by_origin_and_marks_work_entries(): void
    {
        $fulfillment = $this->acceptedWork();
        $ledger = $this->sharedBook();
        app(ProposeWorkBookkeepingEntry::class)->execute($this->worker->user, $fulfillment, $ledger);

        $component = Livewire::actingAs($this->employer->user)->test(BookShow::class, ['ledger' => $ledger]);
        $component
            ->assertSee(__('bookkeeping.origin.iet_workflow'))
            ->assertSeeHtml('data-entry-origin="iet_workflow"')
            ->set('origin', 'manual')
            ->assertDontSeeHtml('data-entry-origin="iet_workflow"')
            ->set('origin', 'iet_workflow')
            ->assertSeeHtml('data-entry-origin="iet_workflow"')
            ->assertSee(__('bookkeeping.origin.open_work'));
    }

    public function test_templates_keep_only_the_schedule_shape_and_create_plans_for_work(): void
    {
        $this->serviceContract();
        $commitment = Commitment::query()->sole();

        // A personal weekly plan becomes a template.
        $context = app(EnsurePersonalContext::class)->execute($this->employer->user);
        $plan = app(CreatePlan::class)->execute($context, $this->employer->user, 'My routine', null, timezone: 'UTC');
        app(CreatePlanScheduleRule::class)->execute($plan, $this->employer->user, PlanScheduleFrequency::Weekly, '2026-10-05', '09:30', 90, 1, [1, 3], [], null, 6, 10, 20, [30]);
        $template = app(SavePlanTemplate::class)->fromPlan($this->employer->user, $plan, 'Mon & Wed mornings', Party::of($this->employer));

        $this->assertSame(PlanScheduleFrequency::Weekly, $template->frequency);
        $this->assertSame('09:30', $template->start_time);
        $this->assertSame([1, 3], $template->weekdays);
        $this->assertSame([30], $template->reminder_offsets);
        $columns = array_keys(DB::table('plan_templates')->where('id', $template->id)->first() ? (array) DB::table('plan_templates')->where('id', $template->id)->first() : []);
        $this->assertEmpty(array_intersect($columns, ['participants', 'starts_on', 'selected_dates', 'ends_on']));

        // Others cannot use it.
        $this->assertSame(0, PlanTemplate::query()->ownedByAny([Party::of($this->worker)])->count());

        Livewire::actingAs($this->employer->user)->test(CommitmentShow::class, ['commitment' => $commitment])
            ->set('planChoice', 'template')
            ->set('templateUuid', $template->uuid)
            ->set('planStartsOn', '2026-10-05')
            ->call('applyTemplate')
            ->assertHasNoErrors();

        $bound = $commitment->fresh('planBinding.plan.occurrences', 'planBinding.plan.participants')->planBinding->plan;
        $this->assertNotSame($plan->id, $bound->id);
        $this->assertSame(6, $bound->occurrences->count());
        $this->assertSame('09:30', $bound->occurrences->sortBy('scheduled_start_at')->first()->scheduled_start_at->format('H:i'));
    }

    public function test_only_plans_already_in_the_deal_can_be_connected(): void
    {
        $contract = $this->serviceContract();
        $commitment = Commitment::query()->sole();
        $bind = app(BindExistingPlan::class);

        $personal = app(CreatePlan::class)->execute(app(EnsurePersonalContext::class)->execute($this->employer->user), $this->employer->user, 'Private', null, timezone: 'UTC');
        $shared = app(CreatePlan::class)->execute($contract->contextBinding->context, $this->employer->user, 'Garden visits', null, timezone: 'UTC');

        $candidates = $bind->candidates($this->employer->user, $commitment)->pluck('id')->all();
        $this->assertContains($shared->id, $candidates);
        $this->assertNotContains($personal->id, $candidates);

        $bind->execute($this->employer->user, $commitment, $shared);
        $this->assertSame($shared->id, (int) $commitment->fresh('planBinding')->planBinding->plan_id);

        $this->expectException(HttpException::class);
        $bind->execute($this->employer->user, $commitment, $personal);
    }

    public function test_automatic_plan_is_created_on_activation(): void
    {
        // Regression: activation crashed on a removed finance relation before 1.10.
        $this->serviceContract(autoPlan: true);
        $commitment = Commitment::query()->sole();
        $this->assertTrue((bool) $commitment->serviceTerm->auto_create_plan);
        $this->assertInstanceOf(Plan::class, $commitment->fresh('planBinding.plan')->planBinding?->plan);
    }

    public function test_contract_form_plan_choice_and_template_fill(): void
    {
        $proposal = app(CreateProposal::class)->execute($this->employer->user, 'Garden proposal', [['actor' => $this->worker, 'role' => 'party']], 'Garden terms.', creatorRole: 'party');
        app(RespondToProposal::class)->execute($proposal, $this->worker->user, ProposalDecisionKind::Accepted);
        $template = app(SavePlanTemplate::class)->create($this->employer->user, Party::of($this->employer), 'Afternoons', [
            'frequency' => PlanScheduleFrequency::Daily, 'timing_mode' => PlanTimingMode::Fixed, 'interval' => 1, 'start_time' => '14:00',
            'duration_minutes' => 120, 'weekdays' => null, 'month_day' => null, 'occurrence_limit' => 3, 'window_before_minutes' => 5,
            'window_after_minutes' => 5, 'reminder_offsets' => [10], 'source_plan_uuid' => null,
        ]);

        Livewire::actingAs($this->employer->user)
            ->withQueryParams(['proposal' => $proposal->uuid])
            ->test(Create::class)
            ->set('serviceWorkflow', true)
            ->set('servicePlanChoice', 'template')
            ->set('serviceTemplateUuid', $template->uuid)
            ->assertSet('serviceStartTime', '14:00')
            ->assertSet('serviceDurationMinutes', 120)
            ->assertSet('serviceFrequency', 'daily')
            ->assertDontSee(__('contracts.service.auto_obligation'))
            ->set('serviceEmployerUsername', $this->employer->user->username)
            ->set('serviceWorkerUsername', $this->worker->user->username)
            ->set('serviceUnitRate', '1000')
            ->set('servicePlanChoice', 'later')
            ->set('timezone', 'UTC')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse((bool) ContractServiceTerm::query()->sole()->auto_create_plan);
    }

    public function test_existing_entries_are_manual_and_adjustments_are_corrections(): void
    {
        $personal = app(EnsurePersonalLedger::class)->execute($this->employer->user);
        $this->assertInstanceOf(Ledger::class, $personal);
        $entry = app(RecordPersonalEntry::class)->execute($personal, $this->employer->user, TransactionType::Expense, '1000', '2026-10-01', $personal->wallets()->firstOrFail());
        $this->assertSame(EntryOrigin::Manual, $entry->fresh()->origin);
        $this->assertNull($entry->source_type);
    }
}
