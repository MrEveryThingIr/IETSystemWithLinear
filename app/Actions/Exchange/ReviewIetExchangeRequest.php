<?php

namespace App\Actions\Exchange;

use App\Actions\Accounting\PostSystemJournalEntry;
use App\IetExchangeDirection;
use App\IetExchangeStatus;
use App\JournalEntryKind;
use App\Models\Actor;
use App\Models\IetExchangeRequest;
use App\Models\Ledger;
use App\Models\User;
use App\PlatformCapability;
use App\Support\IetWallet;
use Illuminate\Support\Facades\DB;

class ReviewIetExchangeRequest
{
    public function __construct(
        private readonly IetWallet $wallet,
        private readonly PostSystemJournalEntry $post,
    ) {}

    public function confirm(IetExchangeRequest $request, User $reviewer, ?string $note = null): IetExchangeRequest
    {
        return $this->review($request, $reviewer, true, $note);
    }

    public function reject(IetExchangeRequest $request, User $reviewer, ?string $note = null): IetExchangeRequest
    {
        return $this->review($request, $reviewer, false, $note);
    }

    private function review(
        IetExchangeRequest $request,
        User $reviewer,
        bool $confirm,
        ?string $note,
    ): IetExchangeRequest {
        abort_unless($reviewer->hasPlatformCapability(PlatformCapability::ManageExchange), 403);

        $current = User::query()->with('actor')->find($reviewer->id);
        abort_unless($current?->actor instanceof Actor, 403);

        $note = trim((string) $note);
        abort_if(mb_strlen($note) > 5000, 422);

        return DB::transaction(function () use ($request, $current, $confirm, $note): IetExchangeRequest {
            $locked = IetExchangeRequest::query()
                ->with(['user.actor', 'valuation'])
                ->lockForUpdate()
                ->findOrFail($request->id);

            abort_unless($locked->status === IetExchangeStatus::Pending, 422);

            $owner = $locked->user;
            abort_unless($owner->actor instanceof Actor, 422);
            abort_if(
                (int) $owner->id === (int) $current->id,
                403,
                'Exchange requests require review by another authorized user.',
            );

            $wallet = $this->wallet->ensure($owner);
            Ledger::query()->whereKey($wallet['ledger']->id)->lockForUpdate()->firstOrFail();

            if ($confirm && $locked->direction === IetExchangeDirection::Deposit) {
                $this->post->execute(
                    $wallet['ledger'],
                    $current->actor,
                    JournalEntryKind::IetExchangeDeposit,
                    now()->toDateString(),
                    'Confirmed IET deposit '.$locked->uuid,
                    [
                        [
                            'account' => $wallet['cash'],
                            'debit_minor' => $locked->iet_amount_minor,
                            'memo' => 'IET issued after confirmed external deposit',
                        ],
                        [
                            'account' => $wallet['exchange_source'],
                            'credit_minor' => $locked->iet_amount_minor,
                            'memo' => 'IET issued after confirmed external deposit',
                        ],
                    ],
                    actingUser: $current,
                    sourceType: 'iet_exchange_request',
                    sourceUuid: $locked->uuid,
                    idempotencyKey: 'iet-exchange:'.$locked->uuid.':deposit-confirm',
                );
            }

            if (! $confirm && $locked->direction === IetExchangeDirection::Cashout) {
                $this->post->execute(
                    $wallet['ledger'],
                    $current->actor,
                    JournalEntryKind::IetExchangeRelease,
                    now()->toDateString(),
                    'Release rejected IET cashout '.$locked->uuid,
                    [
                        [
                            'account' => $wallet['cash'],
                            'debit_minor' => $locked->iet_amount_minor,
                            'memo' => 'Rejected cashout returned to available IET',
                        ],
                        [
                            'account' => $wallet['exchange_sink'],
                            'credit_minor' => $locked->iet_amount_minor,
                            'memo' => 'Rejected cashout returned to available IET',
                        ],
                    ],
                    actingUser: $current,
                    sourceType: 'iet_exchange_request',
                    sourceUuid: $locked->uuid,
                    idempotencyKey: 'iet-exchange:'.$locked->uuid.':cashout-release',
                );
            }

            if ($confirm) {
                $locked->confirm($current->actor, now(), $note !== '' ? $note : null);
            } else {
                $locked->reject($current->actor, now(), $note !== '' ? $note : null);
            }

            return $locked->fresh(['valuation', 'user.actor', 'reviewedBy.user']);
        }, attempts: 3);
    }
}
