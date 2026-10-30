<?php

namespace App\Livewire\Deals;

use App\Actions\Assets\CreateContextAsset;
use App\Actions\Commitments\BindExistingPlan;
use App\Actions\Commitments\CreateCommitmentPlan;
use App\Actions\Deals\LogWorkDay;
use App\Actions\Deals\ManageDealMoney;
use App\Actions\Deals\ManageDealWork;
use App\Actions\Fulfillments\OpenFulfillmentDispute;
use App\Actions\Fulfillments\ResolveFulfillmentDispute;
use App\Actions\Fulfillments\ReviewFulfillment;
use App\Actions\Fulfillments\SubmitFulfillment;
use App\Actions\Planner\TransitionPlanOccurrence;
use App\CommitmentEventType;
use App\FulfillmentDisputeStatus;
use App\FulfillmentReviewDecision;
use App\FulfillmentStatus;
use App\Models\Asset;
use App\Models\Commitment;
use App\Models\CommitmentEvent;
use App\Models\ContractVersion;
use App\Models\ContractVersionParty;
use App\Models\Fulfillment;
use App\Models\FulfillmentDispute;
use App\Models\PlanOccurrence;
use App\Models\Relationship;
use App\Models\User;
use App\PlanOccurrenceStatus;
use App\PlanScheduleFrequency;
use App\Support\CommitmentProgress;
use App\Support\Deals\AgreementTerms;
use App\Support\Deals\DealErrors;
use App\Support\Deals\DealNote;
use App\Support\Deals\DealParties;
use App\Support\Deals\DealTerms;
use App\Support\Deals\WorkLog;
use App\Support\LocalizedNumber;
use App\Support\QuantityAmount;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The Work tab of a Deal: where the Agreement's duties are set up, work is reported and the receiver confirms it.
 * It only drives the existing Commitment, Planner and Fulfillment actions; it owns no records of its own. Day-to-day
 * talk, files and photos belong in the Deal's conversation; here only what is measured is tracked.
 */
class WorkPanel extends Component
{
    use WithFileUploads;

    /** Units a duty can be measured in: the same list the Agreement's price uses, so a price per unit matches its work. */
    public const UNITS = ['item', 'hour', 'day', 'week', 'month', 'project', 'milestone', 'session', 'kilometer', 'square_metre', 'metre', 'cubic_metre', 'kilogram'];

    public Relationship $relationship;

    /** @var array<string, mixed> a new task: title and description only */
    public array $form = [];

    /** @var array<int, mixed> images, video or voice for the task being written */
    public array $taskMedia = [];

    /** @var array<string, string> asking for an inspection of finished work (a milestone or the end) */
    public array $report = [];

    /** @var array<string, string> one day's entry in the work log */
    public array $log = [];

    /** @var array<int, mixed> evidence files for the day being logged */
    public array $logEvidence = [];

    /** @var array<string, string> keyed by "<purpose>-<id>" */
    public array $text = [];

    /** @var array<int, mixed> evidence files for the report being written */
    public array $evidence = [];

    public function mount(Relationship $relationship): void
    {
        $this->relationship = $relationship;
        $this->resetForm();
        $this->resetReport();
        $this->resetLog();
    }

    /** The receiver of the work defines a task: a title to mention in reports, a description and optional media. */
    public function addTask(ManageDealWork $work): void
    {
        $this->resetErrorBag();
        $f = $this->form;
        if (trim((string) ($f['title'] ?? '')) === '' || mb_strlen((string) $f['title']) > 180) {
            $this->addError('form.title', (string) __('deals.errors.work_title'));
        }
        if (count($this->taskMedia) > 5) {
            $this->addError('taskMedia', (string) __('deals.errors.report_files'));
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->guarded(function () use ($work, $f): void {
            $task = $work->createTask($this->relationship, $this->user(), (string) $f['title'], (string) ($f['description'] ?? ''), $this->taskMedia);
            $this->note('work_proposed', ['title' => (string) $task->title]);
            $this->resetForm();
        });
    }

    /** Legacy two-step setup (a pending proposal the receiver confirms): kept for work proposed that way before tasks. */
    public function propose(ManageDealWork $work): void
    {
        $this->resetErrorBag();
        $f = $this->form;
        $quantity = self::quantity((string) ($f['quantity'] ?? ''));
        $unit = ($f['unit_choice'] ?? '') === '__other' ? trim((string) ($f['unit_custom'] ?? '')) : trim((string) ($f['unit_choice'] ?? ''));

        if (trim((string) ($f['title'] ?? '')) === '' || mb_strlen((string) $f['title']) > 180) {
            $this->addError('form.title', (string) __('deals.errors.work_title'));
        }
        if (($f['obligor'] ?? '') === '') {
            $this->addError('form.obligor', (string) __('deals.work.choose_doer'));
        }
        if ($quantity === null) {
            $this->addError('form.quantity', (string) __('deals.errors.report_quantity'));
        }
        if ($unit === '') {
            $this->addError(($f['unit_choice'] ?? '') === '__other' ? 'form.unit_custom' : 'form.unit_choice', (string) __('deals.errors.work_unit'));
        }
        $cycles = max(1, (int) LocalizedNumber::digits((string) ($f['cycles'] ?? '1')));
        if (($f['starts_on'] ?? '') === '' && $cycles > 1) {
            $this->addError('form.starts_on', (string) __('deals.errors.work_start_required'));
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->guarded(function () use ($work, $f, $quantity, $unit, $cycles): void {
            $beneficiary = collect(array_keys($this->partyLabels()))->first(fn ($id) => (int) $id !== (int) $f['obligor']);
            $work->propose($this->relationship, $this->user(), [
                'title' => (string) $f['title'],
                'kind' => 'work',
                'quantity' => $quantity,
                'unit' => $unit,
                'cycles' => $cycles,
                'obligor_party' => (int) $f['obligor'],
                'beneficiary_party' => (int) $beneficiary,
                'starts_on' => (string) ($f['starts_on'] ?? ''),
                'start_time' => (string) ($f['start_time'] ?? '09:00'),
                'duration' => (int) ($f['duration'] ?? 60),
                'every' => (int) ($f['every'] ?? 1),
                'period' => (string) ($f['period'] ?? 'day'),
            ]);
            $this->note('work_proposed', ['title' => (string) $f['title']]);
            $this->resetForm();
        });
    }

    public function confirm(string $id, ManageDealWork $work): void
    {
        $this->guarded(function () use ($work, $id): void {
            $commitment = $work->confirm($this->relationship, $this->user(), $id);
            $this->note('work_confirmed', ['title' => (string) $commitment->title]);
        });
    }

    public function withdraw(string $id, ManageDealWork $work): void
    {
        $this->guarded(fn () => $work->withdraw($this->relationship, $this->user(), $id));
    }

    /** Planned days: the performer reports one day. Marking it done in the Planner is not enough on its own. */
    public function report(int $commitmentId, ?string $occurrenceUuid, string $quantity, SubmitFulfillment $submit, TransitionPlanOccurrence $planner): void
    {
        $this->guarded(function () use ($commitmentId, $occurrenceUuid, $quantity, $submit, $planner): void {
            $commitment = $this->commitments()->findOrFail($commitmentId);
            Gate::forUser($this->user())->authorize('submit', $commitment);
            $notes = trim($this->text['notes-'.$commitmentId.'-'.($occurrenceUuid ?? 'once')] ?? '');

            DB::transaction(function () use ($commitment, $occurrenceUuid, $quantity, $notes, $submit, $planner): void {
                $occurrence = null;
                if ($occurrenceUuid !== null) {
                    $occurrence = PlanOccurrence::query()->where('uuid', $occurrenceUuid)->firstOrFail();
                    if ($occurrence->status !== PlanOccurrenceStatus::Completed) {
                        $occurrence = $planner->markDone($occurrence, $this->user());
                    }
                }
                $submit->execute($commitment, $this->user(), $quantity, occurrence: $occurrence, notes: $notes !== '' ? $notes : null);
            });
        });
    }

    /** One day's entry: when work started and ended, what happened, a rough progress figure and evidence. History only: nothing is confirmed or owed. */
    public function logDay(LogWorkDay $logger): void
    {
        $this->resetErrorBag();
        $l = $this->log;
        $commitment = ($l['commitment'] ?? '') !== '' ? $this->commitments()->find((int) $l['commitment']) : null;
        if (! $commitment instanceof Commitment) {
            $this->addError('log.commitment', (string) __('deals.errors.report_duty'));
        }
        foreach (['date' => 'work_log_date', 'from' => 'work_log_times', 'to' => 'work_log_times'] as $field => $message) {
            if (trim((string) ($l[$field] ?? '')) === '') {
                $this->addError('log.'.$field, (string) __('deals.errors.'.$message));
            }
        }
        $estimate = trim((string) ($l['estimate'] ?? '')) === '' ? null : self::quantity((string) $l['estimate']);
        if (trim((string) ($l['estimate'] ?? '')) !== '' && $estimate === null) {
            $this->addError('log.estimate', (string) __('deals.errors.report_quantity'));
        }
        if (count($this->logEvidence) > 5) {
            $this->addError('logEvidence', (string) __('deals.errors.report_files'));
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->guarded(function () use ($logger, $commitment, $l, $estimate): void {
            $logger->execute($commitment, $this->user(), (string) $l['date'], (string) $l['from'], (string) $l['to'], (string) ($l['note'] ?? ''), $estimate, $this->logEvidence);
            $this->note('work_logged', ['title' => (string) $commitment->title, 'date' => (string) $l['date']]);
            $this->resetLog();
        });
    }

    /** Finished work is inspected at a milestone or at the end: the doer says how much is ready, the receiver measures and confirms. Nothing counts until then. */
    public function submitReport(SubmitFulfillment $submit, CreateContextAsset $assets, CommitmentProgress $progress): void
    {
        $this->resetErrorBag();
        $r = $this->report;
        $quantity = self::quantity((string) ($r['quantity'] ?? ''));
        $commitment = null;
        if (str_starts_with((string) ($r['commitment'] ?? ''), 'rate:')) {
            $this->guarded(function () use (&$commitment, $r): void {
                $commitment = app(ManageDealWork::class)->forRate($this->relationship, $this->user(), substr((string) $r['commitment'], 5));
            });
        } elseif (($r['commitment'] ?? '') !== '') {
            $commitment = $this->commitments()->find((int) $r['commitment']);
        }

        if (! $commitment instanceof Commitment) {
            $this->addError('report.commitment', (string) __('deals.errors.report_duty'));
        }
        if ($quantity === null) {
            $this->addError('report.quantity', (string) __('deals.errors.report_quantity'));
        }
        // A whole-job task is reported as a share of the job (percent); anything else in its own unit.
        if ($commitment instanceof Commitment && $quantity !== null && self::isShare($commitment)) {
            if ((float) $quantity > 100) {
                $this->addError('report.quantity', (string) __('deals.errors.share_over'));
            } else {
                $quantity = QuantityAmount::fromScaledInt((int) round(((float) $quantity / 100) * 10000));
            }
        }
        // Reports waiting for a decision still use up the agreed quantity; the kernel only counts confirmed work.
        if ($commitment instanceof Commitment && $quantity !== null && ! $this->getErrorBag()->has('report.quantity') && QuantityAmount::compare($quantity, $this->openQuantity($commitment, $progress)) > 0) {
            $open = self::isShare($commitment) ? round((float) $this->openQuantity($commitment, $progress) * 100).' %' : self::trimQty($this->openQuantity($commitment, $progress)).' '.(DealTerms::basisLabel($commitment->unit) ?? $commitment->unit);
            $this->addError('report.quantity', (string) __('deals.errors.quantity_exceeds', ['open' => $open]));
        }
        if (count($this->evidence) > 5) {
            $this->addError('evidence', (string) __('deals.errors.report_files'));
        }
        // The day worked (today when left empty) and, when given, the start and end of work: both or neither.
        $zone = TemporalPreferences::timezoneFor($this->user());
        $date = trim((string) ($r['date'] ?? '')) !== '' ? trim((string) $r['date']) : CarbonImmutable::now($zone)->toDateString();
        $from = trim((string) ($r['from'] ?? ''));
        $to = trim((string) ($r['to'] ?? ''));
        $start = $end = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || strtotime($date) === false) {
            $this->addError('report.date', (string) __('deals.errors.work_log_date'));
        } elseif ($from !== '' || $to !== '') {
            if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $from) !== 1 || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $to) !== 1) {
                $this->addError('report.from', (string) __('deals.errors.work_log_times'));
            } else {
                [$start, $end] = [CarbonImmutable::parse($date.' '.$from, $zone), CarbonImmutable::parse($date.' '.$to, $zone)];
                if ($end->lte($start)) {
                    $this->addError('report.from', (string) __('deals.errors.work_log_times'));
                }
            }
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->guarded(function () use ($submit, $assets, $commitment, $quantity, $r, $date, $start, $end): void {
            Gate::forUser($this->user())->authorize('submit', $commitment);

            DB::transaction(function () use ($submit, $assets, $commitment, $quantity, $r, $date, $start, $end): void {
                $context = $commitment->contractVersion->contract->contextBinding?->context;
                $assetIds = collect($this->evidence)->map(fn ($file) => $assets->execute($context, $this->user(), $file, 'owned')->id)->all();
                $note = trim((string) ($r['note'] ?? ''));
                $note = trim(($start === null ? $date.($note !== '' ? ' — ' : '') : '').$note);
                $submit->execute($commitment, $this->user(), $quantity, actualStartAt: $start, actualEndAt: $end, notes: $note !== '' ? $note : null, assetIds: $assetIds);
            });
            $this->note('inspection_requested', ['title' => (string) $commitment->title, 'qty' => self::trimQty((string) $quantity), 'unit' => (string) (DealTerms::basisLabel($commitment->unit) ?? $commitment->unit)]);
            $this->resetReport();
        });
    }

    /** The receiver answers a report: confirm all of it, confirm part of it, ask a question, or not accept it. */
    public function review(int $fulfillmentId, string $decision, ReviewFulfillment $review): void
    {
        $this->guarded(function () use ($fulfillmentId, $decision, $review): void {
            $fulfillment = $this->fulfillment($fulfillmentId);
            $note = trim($this->text['review-'.$fulfillmentId] ?? '');
            $confirmed = trim($this->text['confirm-'.$fulfillmentId] ?? '');
            $parsed = $confirmed === '' ? null : self::quantity($confirmed);
            if ($confirmed !== '' && $parsed === null) {
                $this->addError('text.confirm-'.$fulfillmentId, (string) __('deals.errors.report_quantity'));

                return;
            }
            $review->execute($fulfillment, $this->user(), FulfillmentReviewDecision::from($decision), $note !== '' ? $note : null, $decision === 'accepted' ? $parsed : null);
            $fresh = $fulfillment->fresh(['review']);
            if ($decision === 'accepted') {
                $this->recordAccepted($fresh);
            }
            $this->note(match (true) {
                $decision === 'accepted' && $fresh->isPartlyAccepted() => 'inspection_partly',
                $decision === 'accepted' => 'inspection_accepted',
                $decision === 'rejected' => 'inspection_rejected',
                default => 'inspection_questioned',
            }, ['title' => (string) $fulfillment->commitment->title, 'qty' => self::trimQty($fresh->confirmedQuantity()), 'unit' => (string) (DealTerms::basisLabel($fulfillment->commitment->unit) ?? $fulfillment->commitment->unit)]);
        });
    }

    /** Accepted work with a fixed price is recorded once as an obligation in the Deal's book. Where it cannot be (no shared account yet, no fixed price) the doer can still ask in Money. */
    private function recordAccepted(Fulfillment $fulfillment): void
    {
        try {
            app(ManageDealMoney::class)->recordAccepted($this->relationship, $this->user(), $fulfillment);
        } catch (HttpException $e) {
            abort_if(in_array($e->getStatusCode(), [403, 404], true), $e->getStatusCode());
            report($e);
        }
    }

    /** After a question or a refusal, the performer sends a corrected report for the same work. */
    public function correct(int $fulfillmentId, string $quantity, SubmitFulfillment $submit): void
    {
        $this->guarded(function () use ($fulfillmentId, $quantity, $submit): void {
            $fulfillment = $this->fulfillment($fulfillmentId);
            $note = trim($this->text['notes-fix-'.$fulfillmentId] ?? '');
            $submit->execute($fulfillment->commitment, $this->user(), $quantity, occurrence: $fulfillment->occurrence, actualStartAt: $fulfillment->occurrence === null ? $fulfillment->actual_start_at : null, actualEndAt: $fulfillment->occurrence === null ? $fulfillment->actual_end_at : null, notes: $note !== '' ? $note : null, corrects: $fulfillment);
        });
    }

    /** Schedule a task with the Planner itself: connect one of the Deal's existing plans (created in the Planner) to it. */
    public function linkPlan(int $commitmentId, string $planUuid, BindExistingPlan $bind): void
    {
        $this->guarded(function () use ($commitmentId, $planUuid, $bind): void {
            $commitment = $this->commitments()->findOrFail($commitmentId);
            $plan = $bind->candidates($this->user(), $commitment)->firstWhere('uuid', $planUuid);
            abort_if($plan === null, 422, (string) __('deals.errors.generic'));
            $bind->execute($this->user(), $commitment, $plan);
        });
    }

    /** @var array<int, array<string, string>> the schedule being written, per piece of work */
    public array $sched = [];

    /** Schedule a piece of work with the Planner without leaving the Deal: the plan is created already linked to it. */
    public function schedule(int $commitmentId, CreateCommitmentPlan $create): void
    {
        $this->resetErrorBag('sched');
        $s = $this->sched[$commitmentId] ?? [];
        $date = trim((string) ($s['date'] ?? ''));
        $time = trim((string) ($s['time'] ?? '')) ?: '09:00';
        $hours = LocalizedNumber::digits(trim((string) ($s['hours'] ?? '')) ?: '8');
        $times = (int) (LocalizedNumber::digits(trim((string) ($s['times'] ?? '')) ?: '1'));
        $repeat = (string) ($s['repeat'] ?? 'once');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || strtotime($date) === false) {
            $this->addError('sched.'.$commitmentId.'.date', (string) __('deals.errors.work_start_invalid'));
        }
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
            $this->addError('sched.'.$commitmentId.'.time', (string) __('deals.errors.work_time_invalid'));
        }
        if (preg_match('/^\d{1,2}(\.\d)?$/', $hours) !== 1 || (float) $hours <= 0 || (float) $hours > 24) {
            $this->addError('sched.'.$commitmentId.'.hours', (string) __('deals.errors.work_duration_invalid'));
        }
        if (! in_array($repeat, ['once', 'daily', 'weekly', 'monthly'], true) || $times < 1 || $times > 366) {
            $this->addError('sched.'.$commitmentId.'.times', (string) __('deals.errors.work_cycles'));
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->guarded(function () use ($create, $commitmentId, $date, $time, $hours, $times, $repeat): void {
            $commitment = $this->commitments()->findOrFail($commitmentId);
            abort_if($commitment->planBinding()->exists(), 422, (string) __('deals.errors.generic'));
            $start = CarbonImmutable::parse($date);
            $once = $repeat === 'once' || $times === 1;
            [$frequency, $weekdays, $monthDay] = match (true) {
                $once => [PlanScheduleFrequency::Once, [], null],
                $repeat === 'weekly' => [PlanScheduleFrequency::Weekly, [$start->dayOfWeekIso], null],
                $repeat === 'monthly' => [PlanScheduleFrequency::Monthly, [], $start->day],
                default => [PlanScheduleFrequency::Daily, [], null],
            };
            $create->execute($commitment, $this->user(), $frequency, $date, $time, (int) round((float) $hours * 60), weekdays: $weekdays, occurrenceLimit: $once ? null : $times, monthDay: $monthDay, asDealParty: true);
            unset($this->sched[$commitmentId]);
        });
    }

    public function dispute(int $fulfillmentId, OpenFulfillmentDispute $open): void
    {
        $this->guarded(function () use ($open, $fulfillmentId): void {
            $fulfillment = $this->fulfillment($fulfillmentId);
            $open->execute($fulfillment, $this->user(), trim($this->text['dispute-'.$fulfillmentId] ?? ''));
            $this->note('work_disputed', ['title' => (string) $fulfillment->commitment->title]);
        });
    }

    public function resolve(int $disputeId, string $resolution, ResolveFulfillmentDispute $resolve): void
    {
        $this->guarded(function () use ($disputeId, $resolution, $resolve): void {
            $dispute = FulfillmentDispute::query()->findOrFail($disputeId);
            $resolve->execute($dispute, $this->user(), FulfillmentStatus::from($resolution), trim($this->text['resolve-'.$disputeId] ?? '') ?: null);
        });
    }

    public function render(ManageDealWork $work, CommitmentProgress $progress): View
    {
        $this->relationship->refresh(); // actions work on their own locked copy
        $version = $this->version();
        $mine = $version instanceof ContractVersion ? DealParties::representedBy($version->parties, $this->user())->pluck('id')->all() : [];
        $labels = $this->partyLabels();
        $activeId = $version instanceof ContractVersion ? (int) $version->id : null;

        $pending = collect($work->pending($this->relationship))->map(fn (array $i) => $i + [
            'obligor_name' => $this->nameOf($i['obligor_party'], $labels),
            'beneficiary_name' => $this->nameOf($i['beneficiary_party'], $labels),
            'can_confirm' => in_array($i['beneficiary_party'], $mine, true) && $activeId === $i['version_id'],
            'can_withdraw' => $work->mayWithdraw($this->user(), $i),
            'stale' => $activeId !== $i['version_id'],
        ]);

        $duties = $this->commitments()->with(['obligor.user', 'beneficiary.user', 'contractVersion.contract.contextBinding.context', 'contractVersion.contract.sourceProposalVersion', 'planBinding.plan.occurrences', 'fulfillments.review', 'fulfillments.dispute', 'fulfillments.occurrence', 'fulfillments.assets', 'fulfillments.commitment'])->orderBy('id')->get()
            ->map(fn (Commitment $c) => $this->duty($c, $progress));

        return view('livewire.deals.work-panel', [
            'version' => $version,
            'labels' => $labels,
            'mine' => $mine,
            'pending' => $pending,
            'duties' => $duties,
            'loggable' => $duties->filter(fn ($d) => $d['isObligor'] && ! $d['planned'])->values(),
            'reportable' => $duties->filter(fn ($d) => $d['isObligor'] && ! $d['planned'] && QuantityAmount::compare($d['remaining'], '0') > 0)->values(),
            'rateOptions' => collect($work->rates($this->relationship)),
            'units' => self::UNITS,
            'user' => $this->user(),
        ]);
    }

    /** @return array<string, mixed> */
    private function duty(Commitment $c, CommitmentProgress $progress): array
    {
        $user = $this->user();
        $terms = AgreementTerms::of($c->contractVersion);
        $verifier = $terms['completion']['confirmed_by'] ?? 'beneficiary';
        $occurrences = $c->planBinding?->plan->occurrences->sortBy('scheduled_start_at')->values() ?? collect();
        $accepted = $progress->acceptedQuantity($c);
        $perCycle = $occurrences->isNotEmpty() ? QuantityAmount::fromScaledInt(intdiv(QuantityAmount::toScaledInt($c->quantity), $occurrences->count())) : QuantityAmount::subtractFloorZero($c->quantity, $accepted);
        $live = fn (?int $occId) => $c->fulfillments->where('plan_occurrence_id', $occId)->where('status', '!=', FulfillmentStatus::Corrected)->sortByDesc('id')->first();
        $planned = $occurrences->isNotEmpty();

        $rows = $planned
            ? $occurrences->map(fn (PlanOccurrence $o) => ['occurrence' => $o, 'fulfillment' => $live($o->id)])
            : collect();

        return [
            'commitment' => $c,
            'verifier' => $verifier,
            'perCycle' => $perCycle,
            'accepted' => $accepted,
            'remaining' => $this->openQuantity($c, $progress),
            'planned' => $planned,
            'rows' => $rows,
            'log' => $planned ? null : WorkLog::for($c),
            'media' => $this->taskMedia($c),
            'plans' => $planned || ! Gate::forUser($user)->allows('manage', $c) ? collect() : app(BindExistingPlan::class)->candidates($user, $c),
            'reports' => $planned ? collect() : $c->fulfillments->where('status', '!=', FulfillmentStatus::Corrected)->sortByDesc('id')->values(),
            'isObligor' => Gate::forUser($user)->allows('submit', $c),
            'unit' => DealTerms::basisLabel($c->unit) ?? $c->unit,
            'share' => self::isShare($c),
            'open_ended' => self::isOpen($c),
            'estimate' => (string) (preg_match('/^rate:\d+:([A-Za-z0-9_-]+):\d+$/', (string) $c->derivation_key, $rm) === 1 ? (collect($terms['rates'] ?? [])->firstWhere('key', $rm[1])['estimate'] ?? '') : ($terms['quantity']['value'] ?? '')),
        ];
    }

    /** Quantity still open to report: what is agreed, less what is confirmed and what is waiting for the receiver. */
    private function openQuantity(Commitment $c, CommitmentProgress $progress): string
    {
        $waiting = $c->fulfillments()->whereIn('status', [FulfillmentStatus::Submitted->value, FulfillmentStatus::ClarificationRequested->value])->get(['quantity'])
            ->reduce(fn (string $sum, Fulfillment $f) => QuantityAmount::add($sum, (string) $f->quantity), QuantityAmount::normalize('0'));

        return QuantityAmount::subtractFloorZero(QuantityAmount::subtractFloorZero($c->quantity, $progress->acceptedQuantity($c)), $waiting);
    }

    /** The media the receiver attached to the task. @return list<Asset> */
    private function taskMedia(Commitment $c): array
    {
        $ids = CommitmentEvent::query()->where('commitment_id', $c->id)->where('event_type', CommitmentEventType::TaskMedia->value)->get()
            ->flatMap(fn ($e) => $e->payload['asset_ids'] ?? [])->all();

        return $ids === [] ? [] : Asset::query()->whereIn('id', $ids)->get()->all();
    }

    /** What a report is called: reported, partly accepted, accepted, questioned, not accepted or in dispute. */
    public static function stateOf(?Fulfillment $f, string $verifier): string
    {
        if ($f === null) {
            return 'open';
        }
        if ($f->dispute !== null && $f->dispute->status === FulfillmentDisputeStatus::Open) {
            return 'disputed';
        }

        return match ($f->status) {
            FulfillmentStatus::Accepted => $f->isPartlyAccepted() ? 'partly_accepted' : 'accepted',
            FulfillmentStatus::ClarificationRequested => 'questioned',
            FulfillmentStatus::Rejected => 'not_accepted',
            FulfillmentStatus::Submitted => $verifier === 'none' ? 'reported_final' : 'reported',
            default => $f->status->value,
        };
    }

    private static function trimQty(string $quantity): string
    {
        return str_contains($quantity, '.') ? rtrim(rtrim($quantity, '0'), '.') : $quantity;
    }

    /** A task for the whole job (no measurable unit) is reported as a share of the job. */
    public static function isShare(Commitment $c): bool
    {
        return in_array(strtolower((string) $c->unit), ['project', 'total'], true) && QuantityAmount::compare((string) $c->quantity, '1') === 0;
    }

    /** No fixed total was agreed (a price per unit only): the quantity is open-ended. */
    public static function isOpen(Commitment $c): bool
    {
        return QuantityAmount::compare((string) $c->quantity, '100000') >= 0;
    }

    /** Whole or decimal quantity in any digits and separators, or null when it is not a positive number. */
    private static function quantity(string $value): ?string
    {
        $clean = (string) preg_replace('/[\s,٬،]/u', '', LocalizedNumber::digits(trim($value)));
        if (preg_match('/^\d{1,14}(?:\.\d{1,4})?$/', $clean) !== 1) {
            return null;
        }
        try {
            return QuantityAmount::positive($clean);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** @return array<int, string> party row id => name, for the Agreement in force */
    private function partyLabels(): array
    {
        $version = $this->version();

        return $version instanceof ContractVersion
            ? $version->parties->mapWithKeys(fn ($p) => [$p->id => $p->business->name ?? $p->group->name ?? $p->actor->user->username ?? ''])->all()
            : [];
    }

    /** Party names of a proposal made under an earlier version come from that version's own party rows. */
    private function nameOf(int $partyId, array $labels): string
    {
        if (isset($labels[$partyId])) {
            return $labels[$partyId];
        }
        $party = ContractVersionParty::query()->with(['actor.user', 'business', 'group'])->find($partyId);

        return $party->business->name ?? $party->group->name ?? $party->actor->user->username ?? '';
    }

    private function version(): ?ContractVersion
    {
        $version = $this->relationship->contracts()->latest('id')->first()?->activeVersionRecord();
        $version?->loadMissing('contract', 'parties.actor.user', 'parties.business', 'parties.group');

        return $version;
    }

    /** All Commitments of this Deal's Agreement, whichever version they were set up under. */
    private function commitments()
    {
        return Commitment::query()->whereHas('contractVersion.contract', fn ($q) => $q->where('relationship_id', $this->relationship->id));
    }

    private function fulfillment(int $id): Fulfillment
    {
        $fulfillment = Fulfillment::query()->with(['commitment', 'occurrence'])->findOrFail($id);
        abort_unless($this->commitments()->whereKey($fulfillment->commitment_id)->exists(), 404);

        return $fulfillment;
    }

    /** A labelled step line in the Deal's one conversation. It never decides or blocks anything. @param array<string, mixed> $meta */
    private function note(string $type, array $meta = []): void
    {
        DealNote::forDeal($this->relationship, $this->user(), $type, $meta);
    }

    private function resetForm(): void
    {
        $this->form = ['title' => '', 'description' => ''];
        $this->taskMedia = [];
    }

    private function resetReport(): void
    {
        $this->report = ['commitment' => count($this->report) ? ($this->report['commitment'] ?? '') : '', 'quantity' => '', 'note' => '', 'date' => '', 'from' => '', 'to' => ''];
        $this->evidence = [];
    }

    private function resetLog(): void
    {
        $this->log = ['commitment' => count($this->log) ? ($this->log['commitment'] ?? '') : '', 'date' => '', 'from' => '', 'to' => '', 'note' => '', 'estimate' => ''];
        $this->logEvidence = [];
    }

    private function guarded(callable $call): void
    {
        $this->resetErrorBag('work');
        try {
            $call();
        } catch (HttpException $e) {
            abort_if(in_array($e->getStatusCode(), [403, 404], true), $e->getStatusCode());
            report($e);
            $this->addError('work', DealErrors::localize($e->getMessage()));
        } catch (InvalidArgumentException $e) {
            report($e);
            $this->addError('work', (string) __('deals.errors.generic'));
        }
    }

    private function user(): User
    {
        return auth()->user();
    }
}
