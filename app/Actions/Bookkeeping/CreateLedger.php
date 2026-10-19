<?php

namespace App\Actions\Bookkeeping;

use App\ContextKind;
use App\Enums\Bookkeeping\LedgerKind;
use App\Enums\Bookkeeping\PartyRole;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\LedgerParty;
use App\Models\Context;
use App\Models\User;
use App\Support\Bookkeeping\BookkeepingRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/** Opens a shared or project book; the creator becomes its owner. */
class CreateLedger
{
    public function __construct(private readonly BookkeepingRecorder $recorder) {}

    public function execute(User $user, LedgerKind $kind, string $name, ?string $description = null): Ledger
    {
        abort_if($kind === LedgerKind::Personal, 422, 'Personal books are created automatically.');

        $data = Validator::make(
            ['name' => Str::squish($name), 'description' => $description !== null ? trim($description) : null],
            ['name' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:1000']],
            [],
            ['name' => __('bookkeeping.fields.book_name'), 'description' => __('bookkeeping.fields.description')],
        )->validate();

        $actor = $this->recorder->actor($user);

        return DB::transaction(function () use ($actor, $kind, $data): Ledger {
            $context = Context::query()->create(['kind' => ContextKind::Ledger]);

            $ledger = Ledger::query()->create([
                'kind' => $kind,
                'name' => $data['name'],
                'description' => ($data['description'] ?? '') !== '' ? $data['description'] : null,
                'context_id' => $context->id,
                'owner_actor_id' => $actor->id,
            ]);

            LedgerParty::query()->create([
                'ledger_id' => $ledger->id,
                'actor_id' => $actor->id,
                'role' => PartyRole::Owner,
                'added_by_actor_id' => $actor->id,
            ]);

            $this->recorder->audit($ledger, $actor, 'ledger.created', after: ['kind' => $kind->value, 'name' => $ledger->name]);

            return $ledger->refresh();
        }, 3);
    }
}
