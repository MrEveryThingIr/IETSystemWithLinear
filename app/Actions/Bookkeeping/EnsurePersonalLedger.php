<?php

namespace App\Actions\Bookkeeping;

use App\ContextKind;
use App\Enums\Bookkeeping\CategoryDirection;
use App\Enums\Bookkeeping\LedgerKind;
use App\Enums\Bookkeeping\PartyRole;
use App\Enums\Bookkeeping\WalletKind;
use App\Models\Actor;
use App\Models\Bookkeeping\Category;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\LedgerParty;
use App\Models\Bookkeeping\Wallet;
use App\Models\Context;
use App\Models\User;
use App\Support\Bookkeeping\BookkeepingRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Every person has exactly one personal book, with a cash wallet and
 * everyday categories ready, created the first time they need it.
 */
class EnsurePersonalLedger
{
    /** @var array<string, list<string>> */
    public const DEFAULT_CATEGORIES = [
        'expense' => ['food', 'transport', 'home', 'bills', 'health', 'education', 'work', 'family', 'other_expense'],
        'income' => ['salary', 'work_income', 'sales', 'gift', 'other_income'],
    ];

    public function __construct(private readonly BookkeepingRecorder $recorder) {}

    public function execute(User $user): Ledger
    {
        $actor = $this->recorder->actor($user);

        return DB::transaction(function () use ($actor): Ledger {
            Actor::query()->lockForUpdate()->findOrFail($actor->id);

            $existing = Ledger::query()
                ->where('owner_actor_id', $actor->id)
                ->where('kind', LedgerKind::Personal->value)
                ->first();

            if ($existing instanceof Ledger) {
                return $existing;
            }

            $context = Context::query()->create(['kind' => ContextKind::Ledger]);

            $ledger = Ledger::query()->create([
                'kind' => LedgerKind::Personal,
                'name' => 'personal',
                'context_id' => $context->id,
                'owner_actor_id' => $actor->id,
            ]);

            LedgerParty::query()->create([
                'ledger_id' => $ledger->id,
                'actor_id' => $actor->id,
                'role' => PartyRole::Owner,
                'added_by_actor_id' => $actor->id,
            ]);

            Wallet::query()->create([
                'ledger_id' => $ledger->id,
                'name' => (string) __('bookkeeping.wallets.default_cash'),
                'kind' => WalletKind::Cash,
            ]);

            foreach (self::DEFAULT_CATEGORIES as $direction => $keys) {
                foreach ($keys as $key) {
                    Category::query()->create([
                        'ledger_id' => $ledger->id,
                        'direction' => CategoryDirection::from($direction),
                        'name' => $key,
                        'system_key' => $key,
                    ]);
                }
            }

            $this->recorder->audit($ledger, $actor, 'ledger.created', after: ['kind' => 'personal']);

            return $ledger->refresh();
        }, 3);
    }
}
