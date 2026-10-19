<?php

namespace App\Support\Bookkeeping;

use App\Models\Actor;
use App\Models\Bookkeeping\AuditLog;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\LedgerParty;
use App\Models\Bookkeeping\Transaction;
use App\Models\User;
use App\Support\NotificationOutboxWriter;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Writes the audit trail and notifies the people an entry concerns. */
final class BookkeepingRecorder
{
    public function __construct(private readonly NotificationOutboxWriter $outbox) {}

    public function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function audit(Ledger $ledger, Actor $actor, string $action, ?Transaction $transaction = null, ?array $before = null, ?array $after = null): void
    {
        $request = app()->runningInConsole() ? null : request();

        AuditLog::query()->create([
            'ledger_id' => $ledger->id,
            'transaction_id' => $transaction?->id,
            'actor_id' => $actor->id,
            'on_behalf_of_actor_id' => $transaction?->on_behalf_of_actor_id,
            'action' => $action,
            'before' => $before,
            'after' => $after,
            'ip' => $request?->ip(),
            'user_agent' => $request !== null ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }

    /**
     * Tell people about an entry. Notification failures never undo bookkeeping.
     *
     * @param  iterable<LedgerParty>  $parties
     * @param  array<string, scalar>  $params
     */
    public function notify(iterable $parties, Transaction|Ledger $subject, string $event, array $params, ?Actor $exclude = null): void
    {
        $ledger = $subject instanceof Transaction ? $subject->ledger : $subject;
        $url = $subject instanceof Transaction
            ? route('bookkeeping.books.show', $ledger).'#entry-'.$subject->uuid
            : route('bookkeeping.books.show', $ledger);

        foreach ($parties as $party) {
            $party->loadMissing('actor.user');
            $user = $party->actor->user;

            if ($user === null || $user->status !== 'active' || $user->email_verified_at === null) {
                continue;
            }
            if ($exclude !== null && (int) $party->actor_id === (int) $exclude->id) {
                continue;
            }

            try {
                $this->outbox->request(
                    $user,
                    'bookkeeping:'.$event.':'.$subject->uuid.':'.$user->id,
                    'bookkeeping.event',
                    [
                        'title_key' => 'bookkeeping.notifications.'.$event.'_title',
                        'title_params' => $params,
                        'body_key' => 'bookkeeping.notifications.'.$event.'_body',
                        'body_params' => $params,
                        'url' => $url,
                    ],
                    $ledger->context,
                    $subject instanceof Transaction ? 'bookkeeping_transaction' : 'bookkeeping_ledger',
                    $subject->uuid,
                );
            } catch (Throwable $exception) {
                Log::warning('Bookkeeping notification could not be queued.', ['event' => $event, 'error' => $exception->getMessage()]);
            }
        }
    }

    /** @return array<string, mixed> */
    public static function snapshot(Transaction $transaction): array
    {
        return [
            'uuid' => $transaction->uuid,
            'type' => $transaction->type->value,
            'status' => $transaction->status->value,
            'amount' => (int) $transaction->amount,
            'from_party_id' => $transaction->from_party_id,
            'to_party_id' => $transaction->to_party_id,
            'occurred_on' => $transaction->occurred_on->toDateString(),
            'version' => (int) $transaction->version,
        ];
    }
}
